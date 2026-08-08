#pragma once

#include <QMainWindow>
#include <QLabel>
#include <QPushButton>
#include "traktclient.h"
#include "statestore.h"
#include "showrecommender.h"

class MainWindow : public QMainWindow
{
    Q_OBJECT
public:
    explicit MainWindow(QWidget* parent = nullptr);

private slots:
    void loadNextShow();
    void addToList30();
    void addToList40();
    void addToList60();
    void addToListSleepy();
    void addToListSitcom();
    void skipShow();

private:
    QLabel* posterLabel_;
    QLabel* titleLabel_;
    QLabel* runtimeLabel_;
    QLabel* overviewLabel_;

    // NEW
    QLabel* genreLabel_;
    QLabel* firstAiredLabel_;
    QLabel* seasonCountLabel_;
    
    QPushButton* btn30_;
    QPushButton* btn40_;
    QPushButton* btn60_;
    QPushButton* btnSleepy_;
    QPushButton* btnSitcom_;
    QPushButton* btnSkip_;

    TraktClient* client_;
    StateStore* state_;
    ShowRecommender* recommender_;

    Show currentShow_;

    void displayShow(const Show& show);
    void downloadPoster(const QString& url);
    void addToList(const QString& key);
};
