#!/bin/bash
for t in event_topics grades parent_contacts plan_templates login_codes; do
  echo "== $t"; grep -rIn --include=*.php --include=*.js \
    --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git "$t" . | head -5
done