#pragma once

#include <QObject>
#include <QSet>
#include <QString>

class StateStore : public QObject
{
    Q_OBJECT
public:
    explicit StateStore(const QString& path, QObject* parent = nullptr);

    bool isSkipped(int traktId) const { return skipped_.contains(traktId); }
    void skip(int traktId);
    void save() const;

private:
    QString path_;
    QSet<int> skipped_;
};
