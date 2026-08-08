#pragma once

#include <QObject>
#include <QMap>
#include <QSet>
#include <QString>
#include <QVector>

struct Show {
    int traktId = 0;
    QString title;
    QString overview;
    QString status;

    int runtime = 0;
    QString posterUrl;
    QString fanartUrl;
    QStringList genres;

    int seasonCount = 0;
    QVector<int> episodesPerSeason;
    QString firstAired;
    QString language;
    int totalEpisodes = 0;



};




class TraktClient : public QObject
{
    Q_OBJECT
public:
    explicit TraktClient(const QString& configPath, QObject* parent = nullptr);
    QVector<Show> fetchListShows(const QString& slug) const;
    bool isValid() const { return valid_; }

    // All list keys (30, 40, 60, sleepy, sitcom)
    const QMap<QString, QString>& listMap() const { return lists_; }

    // Primary sources
    QVector<Show> fetchPopularShows();
    QVector<Show> fetchTrendingShows();

    // Fallback source
    QVector<Show> fetchRelatedShows(int traktId);

    // Filtering helpers
    QSet<int> fetchAllListShowIds();
    QSet<int> fetchWatchedShowIds();
    void populateShowDetails(Show& s) const;

    // Add to list
    bool addShowToList(int traktId, const QString& listKey);

private:
    Show parseBasicShow(const QJsonObject& obj) const;
    QString clientId_;
    QString accessToken_;
    QString username_;
    QMap<QString, QString> lists_;
    bool valid_ = false;


    QByteArray post(const QString& path, const QByteArray& body);

QByteArray get(const QString& path, const QString& query) const;
Show parseShow(const QJsonObject& obj) const;
int fetchMaxRuntimeForShow(int traktId) const;

void fetchSeasonEpisodeInfo(Show& s) const;


};
