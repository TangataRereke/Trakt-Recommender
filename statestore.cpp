#include "statestore.h"

#include <QFile>
#include <QJsonDocument>
#include <QJsonObject>
#include <QJsonArray>

StateStore::StateStore(const QString& path, QObject* parent)
    : QObject(parent), path_(path)
{
    QFile f(path_);
    if (!f.open(QIODevice::ReadOnly))
        return;

    QByteArray data = f.readAll();
    f.close();

    QJsonParseError err{};
    QJsonDocument doc = QJsonDocument::fromJson(data, &err);
    if (err.error != QJsonParseError::NoError || !doc.isObject())
        return;

    QJsonObject obj = doc.object();
    QJsonArray arr = obj.value("skipped_shows").toArray();
    for (const auto& v : arr) {
        int id = v.toInt();
        if (id > 0) skipped_.insert(id);
    }
}

void StateStore::skip(int traktId)
{
    skipped_.insert(traktId);
    save();
}

void StateStore::save() const
{
    QJsonObject obj;
    QJsonArray arr;
    for (int id : skipped_) {
        arr.append(id);
    }
    obj.insert("skipped_shows", arr);

    QJsonDocument doc(obj);
    QFile f(path_);
    if (!f.open(QIODevice::WriteOnly | QIODevice::Truncate))
        return;
    f.write(doc.toJson(QJsonDocument::Indented));
    f.close();
}
