#!/bin/sh
# Affiche le dernier land créé et ses infos spectrales
LANDS_DIR="$(dirname "$0")/storage/lands"
LATEST=$(ls -1t "$LANDS_DIR"/*.json 2>/dev/null | head -n1)
if [ -z "$LATEST" ]; then
  echo "Aucun land trouvé dans $LANDS_DIR"
  exit 1
fi
echo "Dernier land : $LATEST"
cat "$LATEST" | grep -E '"username"|"land_program"|"lambda_nm"'
