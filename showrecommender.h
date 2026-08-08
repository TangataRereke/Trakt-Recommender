#pragma once

#include <QDebug>

#include <QObject>
#include <QVector>
#include <QSet>
#include "traktclient.h"
#include "statestore.h"

class ShowRecommender : public QObject
{
    Q_OBJECT
public:
    ShowRecommender(TraktClient* client, StateStore* state, QObject* parent = nullptr);
    void markAsInList(int traktId) { inLists_.insert(traktId); }
    Show nextShow();

private:
    TraktClient* client_;
    StateStore* state_;

    QVector<Show> candidates_;
    int index_ = 0;

    QSet<int> inLists_;
    QSet<int> watched_;

    QStringList cycle_ = { "30", "40", "60", "sleepy", "sitcom" };
    int cycleIndex_ = 0;

    void ensureLoaded();
    bool isEligible(const Show& s) const;

    Show fallbackRelated();
};
