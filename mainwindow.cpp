#include "mainwindow.h"

#include <QVBoxLayout>
#include <QWidget>
#include <QNetworkAccessManager>
#include <QNetworkReply>
#include <QPixmap>
#include <QUrl>

MainWindow::MainWindow(QWidget* parent)
    : QMainWindow(parent)
{
    client_ = new TraktClient("config.json", this);
    state_ = new StateStore("state.json", this);
    recommender_ = new ShowRecommender(client_, state_, this);

    posterLabel_ = new QLabel;
    posterLabel_->setFixedSize(300, 450);
    posterLabel_->setScaledContents(true);
    
    titleLabel_ = new QLabel;
    titleLabel_->setWordWrap(true);
    titleLabel_->setStyleSheet("font-size: 22px; font-weight: bold; margin-bottom: 4px;");
    
    runtimeLabel_ = new QLabel;
    runtimeLabel_->setStyleSheet("font-size: 14px; color: #a0a0a0;");

    overviewLabel_ = new QLabel;
    overviewLabel_->setWordWrap(true);
    overviewLabel_->setStyleSheet("font-size: 13px; margin-top: 6px; margin-bottom: 6px;");
    
    genreLabel_ = new QLabel;
    firstAiredLabel_ = new QLabel;
    seasonCountLabel_ = new QLabel;

    btn30_ = new QPushButton("Add to 30-min list");
    btn40_ = new QPushButton("Add to 40-min list");
    btn60_ = new QPushButton("Add to 60-min list");
    btnSleepy_ = new QPushButton("Add to Sleepy list");
    btnSitcom_ = new QPushButton("Add to Sitcom list");
    btnSkip_ = new QPushButton("Skip");

    connect(btn30_, &QPushButton::clicked, this, &MainWindow::addToList30);
    connect(btn40_, &QPushButton::clicked, this, &MainWindow::addToList40);
    connect(btn60_, &QPushButton::clicked, this, &MainWindow::addToList60);
    connect(btnSleepy_, &QPushButton::clicked, this, &MainWindow::addToListSleepy);
    connect(btnSitcom_, &QPushButton::clicked, this, &MainWindow::addToListSitcom);
    connect(btnSkip_, &QPushButton::clicked, this, &MainWindow::skipShow);

    // Right side vertical layout (Details + Buttons)
    auto detailsLayout = new QVBoxLayout;
    detailsLayout->addWidget(titleLabel_);
    detailsLayout->addWidget(runtimeLabel_);
    detailsLayout->addWidget(overviewLabel_);
    detailsLayout->addWidget(genreLabel_);
    detailsLayout->addWidget(firstAiredLabel_);
    detailsLayout->addWidget(seasonCountLabel_);
    detailsLayout->addSpacing(10);
    detailsLayout->addWidget(btn30_);
    detailsLayout->addWidget(btn40_);
    detailsLayout->addWidget(btn60_);
    detailsLayout->addWidget(btnSleepy_);
    detailsLayout->addWidget(btnSitcom_);
    detailsLayout->addSpacing(5);
    detailsLayout->addWidget(btnSkip_);
    detailsLayout->addStretch(); // Push content up neatly

    // Main horizontal layout (Left: Poster, Right: Details)
    auto mainLayout = new QHBoxLayout;
    mainLayout->addWidget(posterLabel_, 0, Qt::AlignTop);
    mainLayout->addSpacing(15);
    mainLayout->addLayout(detailsLayout);

    auto central = new QWidget;
    central->setLayout(mainLayout);
    setCentralWidget(central);

    setWindowTitle("Trakt Recommender");

    loadNextShow();
}

void MainWindow::loadNextShow()
{
    currentShow_ = recommender_->nextShow();
    displayShow(currentShow_);
}

void MainWindow::displayShow(const Show& show)
{
    titleLabel_->setText(show.title);

    // Runtime
    if (show.runtime > 0)
        runtimeLabel_->setText(QString("Max runtime: %1 min").arg(show.runtime));
    else
        runtimeLabel_->setText("Runtime: unknown");

    // Overview
    overviewLabel_->setText(show.overview);

    // Genres
    if (!show.genres.isEmpty())
        genreLabel_->setText("Genres: " + show.genres.join(", "));
    else
        genreLabel_->setText("Genres: unknown");

    // First aired
    if (!show.firstAired.isEmpty())
        firstAiredLabel_->setText("First aired: " + show.firstAired);
    else
        firstAiredLabel_->setText("First aired: unknown");

    // Season count
    if (show.seasonCount > 0)
        seasonCountLabel_->setText(QString("Seasons: %1").arg(show.seasonCount));
    else
        seasonCountLabel_->setText("Seasons: unknown");


    // Poster
    if (!show.posterUrl.isEmpty())
        downloadPoster(show.posterUrl);
    else
        posterLabel_->setPixmap(QPixmap());

    if (show.totalEpisodes > 0)
        seasonCountLabel_->setText(QString("Episodes: %1").arg(show.totalEpisodes));
    else
        seasonCountLabel_->setText("Episodes: unknown");
    
}

void MainWindow::downloadPoster(const QString& url)
{
    qDebug() << "[DIAGNOSTIC Poster Download] Initiating download URL:" << url;

    auto* mgr = new QNetworkAccessManager(this);
    QNetworkRequest req{ QUrl(url) };
    
    // Ensure redirects are allowed (TMDb / Trakt CDN links often redirect)
    req.setAttribute(QNetworkRequest::RedirectPolicyAttribute, QNetworkRequest::NoLessSafeRedirectPolicy);

    QNetworkReply* reply = mgr->get(req);

    connect(reply, &QNetworkReply::finished, this, [this, reply, mgr]() {
        int status = reply->attribute(QNetworkRequest::HttpStatusCodeAttribute).toInt();
        qDebug() << "[DIAGNOSTIC Poster Network] HTTP Status:" << status;

        if (reply->error() != QNetworkReply::NoError) {
            qDebug() << "[DIAGNOSTIC Poster Error]:" << reply->errorString();
        }

        QByteArray data = reply->readAll();
        qDebug() << "[DIAGNOSTIC Poster Bytes Received]:" << data.size();

        QPixmap pix;
        bool loaded = pix.loadFromData(data);
        qDebug() << "[DIAGNOSTIC QPixmap Success]:" << loaded;

        if (loaded) {
            posterLabel_->setPixmap(pix);
        } else {
            qDebug() << "[DIAGNOSTIC QPixmap Failed] First 50 bytes of response:" << data.left(50);
        }

        reply->deleteLater();
        mgr->deleteLater();
    });
}


void MainWindow::addToList(const QString& key)
{
    if (currentShow_.traktId <= 0) return;
    client_->addShowToList(currentShow_.traktId, key);
    
    // Refresh local set immediately
    recommender_->markAsInList(currentShow_.traktId);

    loadNextShow();
}

void MainWindow::addToList30()    { addToList("30"); }
void MainWindow::addToList40()    { addToList("40"); }
void MainWindow::addToList60()    { addToList("60"); }
void MainWindow::addToListSleepy(){ addToList("sleepy"); }
void MainWindow::addToListSitcom(){ addToList("sitcom"); }

void MainWindow::skipShow()
{
    if (currentShow_.traktId > 0)
        state_->skip(currentShow_.traktId);
    loadNextShow();
}
