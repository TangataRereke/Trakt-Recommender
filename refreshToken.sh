#!/bin/bash

CONFIG_FILE="config.json"

CLIENT_ID=$(jq -r '.client_id' "$CONFIG_FILE")
CLIENT_SECRET=$(jq -r '.client_secret' "$CONFIG_FILE")
REFRESH_TOKEN=$(jq -r '.refresh_token' "$CONFIG_FILE")

RESPONSE=$(curl -s -X POST "https://api.trakt.tv/oauth/token" \
  -H "Content-Type: application/json" \
  -d '{
    "refresh_token": "'"$REFRESH_TOKEN"'",
    "client_id": "'"$CLIENT_ID"'",
    "client_secret": "'"$CLIENT_SECRET"'",
    "redirect_uri": "urn:ietf:wg:oauth:2.0:oob",
    "grant_type": "refresh_token"
  }')

NEW_ACCESS=$(echo "$RESPONSE" | jq -r '.access_token')
NEW_REFRESH=$(echo "$RESPONSE" | jq -r '.refresh_token')

if [ "$NEW_ACCESS" != "null" ] && [ -n "$NEW_ACCESS" ]; then
    TMP_FILE=$(mktemp)
    jq --arg a "$NEW_ACCESS" --arg r "$NEW_REFRESH" \
       '.access_token = $a | .refresh_token = $r' "$CONFIG_FILE" > "$TMP_FILE" && mv "$TMP_FILE" "$CONFIG_FILE"
    echo "Tokens successfully refreshed."
else
    echo "Failed to refresh token response: $RESPONSE"
fi
