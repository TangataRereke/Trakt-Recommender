#include "showrecommender.h"
#include <QRandomGenerator>

ShowRecommender::ShowRecommender(TraktClient* client, StateStore* state, QObject* parent)
    : QObject(parent),
      client_(client),
      state_(state)
{
}

void ShowRecommender::ensureLoaded()
{
    if (!candidates_.isEmpty())
        return;

    if (!client_ || !client_->isValid())
        return;

    inLists_ = client_->fetchAllListShowIds();
    watched_ = client_->fetchWatchedShowIds();

    QVector<Show> popular = client_->fetchPopularShows();
    QVector<Show> trending = client_->fetchTrendingShows();

    candidates_ = popular;
    for (const Show& s : trending)
        candidates_.append(s);

    // Uniform random order
    std::shuffle(candidates_.begin(), candidates_.end(),
                 *QRandomGenerator::global());

    index_ = 0;
}


bool ShowRecommender::isEligible(const Show& s) const
{
    // DEBUG 3B: Output candidate evaluation details
    bool inList = inLists_.contains(s.traktId);
    qDebug() << "[DEBUG Eligibility] Checking Show:" << s.title 
             << "ID:" << s.traktId 
             << "Is in inLists_ set?" << inList;

    if (s.traktId <= 0) {
        qDebug() << "Reject" << s.title << "reason: invalid traktId";
        return false;
    }
    if (s.status.compare("ended", Qt::CaseInsensitive) != 0) {
        qDebug() << "Reject" << s.title << "reason: not ended, status=" << s.status;
        return false;
    }
    if (s.status.compare("canceled", Qt::CaseInsensitive) == 0) {
        qDebug() << "Reject" << s.title << "reason: canceled";
        return false;
    }
    if (inList) {
        qDebug() << "Reject" << s.title << "reason: in user lists";
        return false;
    }
    if (watched_.contains(s.traktId)) {
        qDebug() << "Reject" << s.title << "reason: watched";
        return false;
    }
    if (state_->isSkipped(s.traktId)) {
        qDebug() << "Reject" << s.title << "reason: manually skipped";
        return false;
    }
    // In ShowRecommender::isEligible
    if (s.language.compare("en", Qt::CaseInsensitive) != 0) {
        qDebug() << "Reject" << s.title << "reason: non-English language (" << s.language << ")";
        return false;
    }
    return true;
}

Show ShowRecommender::fallbackRelated()
{
    const QMap<QString, QString>& lists = client_->listMap();

    for (int attempt = 0; attempt < cycle_.size(); ++attempt) {
        QString key = cycle_.at(cycleIndex_);
        cycleIndex_ = (cycleIndex_ + 1) % cycle_.size();

        if (!lists.contains(key)) continue;

        QString slug = lists.value(key);
        QVector<Show> listShows = client_->fetchListShows(slug);
        if (listShows.isEmpty()) continue;

        int randomIndex = QRandomGenerator::global()->bounded(listShows.size());
        int seedId = listShows[randomIndex].traktId;

        QVector<Show> related = client_->fetchRelatedShows(seedId);

        for (Show& s : related) {
            if (isEligible(s)) {
                // Populate heavy details ONLY for the selected fallback show!
                client_->populateShowDetails(s);
                return s;
            }
        }
    }

    Show empty;
    empty.title = "No more recommendations";
    empty.overview = "All lists exhausted.";
    return empty;
}


Show ShowRecommender::nextShow()
{
    ensureLoaded();

    qDebug() << "Candidates loaded:" << candidates_.size();
    qDebug() << "inLists size:" << inLists_.size();
    qDebug() << "watched size:" << watched_.size();

    while (index_ < candidates_.size()) {
        Show s = candidates_.at(index_);
        index_++;

        bool ok = isEligible(s);
        qDebug() << "Checking candidate:" << s.traktId << s.title
                 << "status=" << s.status
                 << "eligible=" << ok;

        if (ok) {
            // Populate expensive details (sampled runtime & episodes) ONLY when selected!
            client_->populateShowDetails(s);
            return s;
        }
    }

    qDebug() << "Falling back to related";
    return fallbackRelated();
}

