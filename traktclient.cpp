#include "traktclient.h"

#include <QFile>
#include <QJsonDocument>
#include <QJsonObject>
#include <QJsonArray>
#include <QNetworkAccessManager>
#include <QNetworkRequest>
#include <QNetworkReply>
#include <QEventLoop>

static const QString BASE_URL = QStringLiteral("https://api.trakt.tv");

TraktClient::TraktClient(const QString& configPath, QObject* parent)
    : QObject(parent)
{
    QFile f(configPath);
    if (!f.open(QIODevice::ReadOnly)) {
        qWarning("Failed to open config.json");
        return;
    }

    auto data = f.readAll();
    f.close();

    QJsonParseError err{};
    auto doc = QJsonDocument::fromJson(data, &err);
    if (err.error != QJsonParseError::NoError || !doc.isObject()) {
        qWarning("Invalid config.json");
        return;
    }

    QJsonObject obj = doc.object();
    clientId_ = obj.value("client_id").toString();
    accessToken_ = obj.value("access_token").toString();
    username_ = obj.value("username").toString();

    qDebug() << "clientId =" << clientId_;
    qDebug() << "accessToken =" << accessToken_;
    qDebug() << "username =" << username_;

    QJsonObject listsObj = obj.value("lists").toObject();
    for (auto it = listsObj.begin(); it != listsObj.end(); ++it)
        lists_.insert(it.key(), it.value().toString());

    valid_ = !(clientId_.isEmpty() || accessToken_.isEmpty() || username_.isEmpty());
}

QByteArray TraktClient::get(const QString& path, const QString& query) const
{
    QNetworkAccessManager mgr;
    QUrl url(BASE_URL + path);
    if (!query.isEmpty())
        url.setQuery(query);

    QNetworkRequest req{ QUrl(url) };
    req.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");
    req.setRawHeader("trakt-api-version", "2");
    req.setRawHeader("trakt-api-key", clientId_.toUtf8());
    req.setRawHeader("Authorization", ("Bearer " + accessToken_).toUtf8());
    req.setAttribute(QNetworkRequest::Http2AllowedAttribute, false);


    QNetworkReply* reply = mgr.get(req);
    QEventLoop loop;
    QObject::connect(reply, &QNetworkReply::finished, &loop, &QEventLoop::quit);
    loop.exec();

    QByteArray data = reply->readAll();
    reply->deleteLater();
    return data;
}

Show TraktClient::parseBasicShow(const QJsonObject& obj) const
{
    Show s;
    QJsonObject showObj = obj.contains("show") ? obj.value("show").toObject() : obj;

    s.traktId = showObj.value("ids").toObject().value("trakt").toInt();
    s.title = showObj.value("title").toString();
    s.status = showObj.value("status").toString();

    return s;
}

QByteArray TraktClient::post(const QString& path, const QByteArray& body)
{
    QNetworkAccessManager mgr;
    QUrl url(BASE_URL + path);

    QNetworkRequest req{ QUrl(url) };
    req.setHeader(QNetworkRequest::ContentTypeHeader, "application/json");
    req.setRawHeader("trakt-api-version", "2");
    req.setRawHeader("trakt-api-key", clientId_.toUtf8());
    req.setRawHeader("Authorization", ("Bearer " + accessToken_).toUtf8());
    req.setAttribute(QNetworkRequest::Http2AllowedAttribute, false);


    QNetworkReply* reply = mgr.post(req, body);
    QEventLoop loop;
    QObject::connect(reply, &QNetworkReply::finished, &loop, &QEventLoop::quit);
    loop.exec();

    QByteArray data = reply->readAll();
    reply->deleteLater();
    return data;
}
Show TraktClient::parseShow(const QJsonObject& obj) const
{
    Show s;

    // Basic fields
    QJsonObject ids = obj.value("ids").toObject();
    s.traktId = ids.value("trakt").toInt();
    s.title = obj.value("title").toString();
    s.overview = obj.value("overview").toString();
    s.status = obj.value("status").toString();
    s.language = obj.value("language").toString();

    // Genres
    QJsonArray genresArr = obj.value("genres").toArray();
    for (const auto& g : genresArr)
        s.genres.append(g.toString());

    // Images parsing
    if (obj.contains("images")) {
        QJsonObject images = obj.value("images").toObject();

        QString posterPath;
        if (images.contains("poster")) {
            QJsonValue pVal = images.value("poster");

            // 1. Handle poster as an Array (Trakt extended format)
            if (pVal.isArray()) {
                QJsonArray pArr = pVal.toArray();
                if (!pArr.isEmpty()) {
                    QJsonValue firstItem = pArr.at(0);
                    if (firstItem.isString()) {
                        posterPath = firstItem.toString();
                    } else if (firstItem.isObject()) {
                        QJsonObject pObj = firstItem.toObject();
                        posterPath = pObj.value("full").toString();
                        if (posterPath.isEmpty()) posterPath = pObj.value("medium").toString();
                        if (posterPath.isEmpty()) posterPath = pObj.value("thumb").toString();
                    }
                }
            } 
            // 2. Handle poster as a direct string
            else if (pVal.isString()) {
                posterPath = pVal.toString();
            } 
            // 3. Handle poster as a single Object
            else if (pVal.isObject()) {
                QJsonObject pObj = pVal.toObject();
                posterPath = pObj.value("full").toString();
                if (posterPath.isEmpty()) posterPath = pObj.value("medium").toString();
                if (posterPath.isEmpty()) posterPath = pObj.value("thumb").toString();
            }
        }
        
        if (!posterPath.isEmpty()) {
            // Strip .webp extension cleanly if present so Trakt serves the default JPG
            if (posterPath.endsWith(".webp", Qt::CaseInsensitive)) {
                posterPath.chop(5); // Drops '.webp'
            }
        
            if (posterPath.startsWith("//")) {
                s.posterUrl = "https:" + posterPath;
            } else if (posterPath.startsWith("http://") || posterPath.startsWith("https://")) {
                s.posterUrl = posterPath;
            } else if (posterPath.startsWith("media.trakt.tv") || posterPath.startsWith("assets.trakt.tv")) {
                s.posterUrl = "https://" + posterPath;
            } else {
                // TMDB relative path (e.g. "/abc12345.jpg")
                if (!posterPath.startsWith("/")) {
                    posterPath = "/" + posterPath;
                }
                s.posterUrl = "https://image.tmdb.org/t/p/original" + posterPath;
            }
        }
    }

    if (obj.contains("first_aired"))
        s.firstAired = obj.value("first_aired").toString();

    qDebug() << "[DEBUG Show Parsed]" << s.title << "| Poster URL:" << s.posterUrl;

    return s;
}

QVector<Show> TraktClient::fetchPopularShows()
{
    QVector<Show> result;
    QByteArray data = get("/shows/popular", "extended=full,images");
    QJsonDocument doc = QJsonDocument::fromJson(data);
    if (!doc.isArray()) return result;

    for (const auto& v : doc.array()) {
        if (!v.isObject()) continue;
        
        // Basic parse first
        Show s = parseShow(v.toObject());
        
        // ONLY fetch runtimes/season details later in the pipeline 
        // once you know the show is eligible, NOT here in the raw fetch loop.
        result.push_back(s);
    }
    return result;
}

QVector<Show> TraktClient::fetchListShows(const QString& slug) const
{
    QVector<Show> result;
    if (!isValid()) return result;

    QString path = QString("/users/%1/lists/%2/items/shows").arg(username_, slug);
    QByteArray data = get(path, "extended=show,full,images");
    QJsonDocument doc = QJsonDocument::fromJson(data);
    if (!doc.isArray()) return result;

    for (const auto& v : doc.array()) {
        if (!v.isObject()) continue;
        QJsonObject showObj = v.toObject().value("show").toObject();
        if (showObj.isEmpty()) continue;

        Show s = parseShow(showObj);
        // REMOVED fetchMaxRuntimeForShow and fetchSeasonEpisodeInfo
        result.push_back(s);
    }
    return result;
}


QVector<Show> TraktClient::fetchTrendingShows()
{
    QVector<Show> result;
    QByteArray data = get("/shows/trending", "extended=show,full,images");
    QJsonDocument doc = QJsonDocument::fromJson(data);
    if (!doc.isArray()) return result;

    for (const auto& v : doc.array()) {
        if (!v.isObject()) continue;
        QJsonObject showObj = v.toObject().value("show").toObject();
        
        Show s = parseShow(showObj);
        result.push_back(s);
    }
    return result;
}

void TraktClient::populateShowDetails(Show& s) const
{
    s.runtime = fetchMaxRuntimeForShow(s.traktId);
    fetchSeasonEpisodeInfo(s);
}

QVector<Show> TraktClient::fetchRelatedShows(int traktId)
{
    QVector<Show> result;
    QByteArray data = get(QString("/shows/%1/related").arg(traktId), "extended=full,images");
    QJsonDocument doc = QJsonDocument::fromJson(data);
    if (!doc.isArray()) return result;

    for (const auto& v : doc.array()) {
        if (!v.isObject()) continue;
        Show s = parseShow(v.toObject());
        // REMOVED fetchMaxRuntimeForShow and fetchSeasonEpisodeInfo
        result.push_back(s);
    }
    return result;
}

int TraktClient::fetchMaxRuntimeForShow(int traktId) const
{
    // 1. Fetch seasons list
    QByteArray data = get(QString("/shows/%1/seasons").arg(traktId), "");
    QJsonDocument doc = QJsonDocument::fromJson(data);
    if (!doc.isArray() || doc.array().isEmpty()) return 0;

    int firstSeason = -1;
    int lastSeason = -1;

    for (const auto& v : doc.array()) {
        int seasonNum = v.toObject().value("number").toInt();
        if (seasonNum > 0) { // Skip Specials (Season 0)
            if (firstSeason == -1) firstSeason = seasonNum;
            lastSeason = seasonNum;
        }
    }

    if (firstSeason == -1) return 0;

    QVector<int> sampledRuntimes;

    auto sampleSeason = [&](int seasonNum, bool takeFirstTwo) {
        QByteArray sData = get(QString("/shows/%1/seasons/%2").arg(traktId).arg(seasonNum), "extended=full");
        QJsonDocument sDoc = QJsonDocument::fromJson(sData);
        if (!sDoc.isArray()) return;

        QJsonArray eps = sDoc.array();
        if (eps.isEmpty()) return;

        if (takeFirstTwo) {
            for (int i = 0; i < qMin(2, eps.size()); ++i) {
                int rt = eps[i].toObject().value("runtime").toInt();
                if (rt > 0) sampledRuntimes.append(rt);
            }
        } else {
            int start = qMax(0, eps.size() - 2);
            for (int i = start; i < eps.size(); ++i) {
                int rt = eps[i].toObject().value("runtime").toInt();
                if (rt > 0) sampledRuntimes.append(rt);
            }
        }
    };

    // First 2 of Season 1
    sampleSeason(firstSeason, true);

    // Last 2 of Final Season (if distinct)
    if (lastSeason != firstSeason) {
        sampleSeason(lastSeason, false);
    }

    if (sampledRuntimes.isEmpty()) return 0;

    // Calculate median
    std::sort(sampledRuntimes.begin(), sampledRuntimes.end());
    
    int medianRuntime = 0;
    int n = sampledRuntimes.size();
    if (n % 2 == 0) {
        medianRuntime = (sampledRuntimes[n / 2 - 1] + sampledRuntimes[n / 2]) / 2;
    } else {
        medianRuntime = sampledRuntimes[n / 2];
    }

    qDebug() << "[DEBUG Runtime Median]" << traktId << "Sampled Runtimes:" << sampledRuntimes << "Calculated Median:" << medianRuntime;
    return medianRuntime;
}

void TraktClient::fetchSeasonEpisodeInfo(Show& s) const
{
    QByteArray data = get(QString("/shows/%1/seasons").arg(s.traktId),
                          "extended=episodes");
    QJsonDocument doc = QJsonDocument::fromJson(data);
    if (!doc.isArray()) return;

    s.seasonCount = doc.array().size();

    for (const auto& sv : doc.array()) {
        QJsonArray eps = sv.toObject().value("episodes").toArray();
        s.totalEpisodes += eps.size();
    }
}


QSet<int> TraktClient::fetchAllListShowIds()
{
    QSet<int> ids;
    qDebug() << "================ LIST FETCH PAGINATION ================";

    for (auto it = lists_.begin(); it != lists_.end(); ++it) {
        QString listKey = it.key();
        QString slug = it.value();

        int page = 1;
        int listTotalCount = 0;

        while (true) {
            // Trakt API endpoint for list items
            QString path = QString("/users/%1/lists/%2/items/shows").arg(username_, slug);
            QString query = QString("extended=full&limit=250&page=%1").arg(page);

            QByteArray data = get(path, query);
            QJsonDocument doc = QJsonDocument::fromJson(data);

            // Fallback to general items if items/shows is empty/invalid
            if (!doc.isArray() || doc.array().isEmpty()) {
                path = QString("/users/%1/lists/%2/items").arg(username_, slug);
                data = get(path, query);
                doc = QJsonDocument::fromJson(data);
            }

            if (!doc.isArray() || doc.array().isEmpty()) {
                // Reached the end of pages for this list
                break;
            }

            QJsonArray arr = doc.array();
            int pageAdded = 0;

            for (const auto& v : arr) {
                QJsonObject item = v.toObject();
                QJsonObject showObj;

                if (item.contains("show")) {
                    showObj = item.value("show").toObject();
                } else if (item.value("type").toString() == "show") {
                    showObj = item.value("show").toObject();
                } else {
                    showObj = item;
                }

                int traktId = showObj.value("ids").toObject().value("trakt").toInt();
                if (traktId > 0) {
                    ids.insert(traktId);
                    pageAdded++;
                }
            }

            listTotalCount += pageAdded;

            // If we received fewer items than the limit, we hit the final page
            if (arr.size() < 250) {
                break;
            }

            page++;
        }

        qDebug() << "[LIST FETCH]" << listKey << "(" << slug << ") -> Total shows across" << page << "page(s):" << listTotalCount;
    }

    qDebug() << "[LIST FETCH COMPLETE] Total unique IDs cached in inLists_:" << ids.size();
    qDebug() << "========================================================";

    return ids;
}

QSet<int> TraktClient::fetchWatchedShowIds()
{
    QSet<int> ids;
    QByteArray data = get("/sync/history/shows", "");
    QJsonDocument doc = QJsonDocument::fromJson(data);
    if (!doc.isArray()) return ids;

    for (const auto& v : doc.array()) {
        QJsonObject showObj = v.toObject().value("show").toObject();
        int id = showObj.value("ids").toObject().value("trakt").toInt();
        if (id > 0) ids.insert(id);
    }
    return ids;
}

bool TraktClient::addShowToList(int traktId, const QString& listKey)
{
    if (!lists_.contains(listKey)) return false;
    QString slug = lists_.value(listKey);

    QString path = QString("/users/%1/lists/%2/items").arg(username_, slug);

    QJsonObject idsObj;
    idsObj.insert("trakt", traktId);

    QJsonObject showObj;
    showObj.insert("ids", idsObj);

    QJsonArray shows;
    shows.append(showObj);

    QJsonObject root;
    root.insert("shows", shows);

    QJsonDocument doc(root);
    post(path, doc.toJson(QJsonDocument::Compact));
    return true;
}
