#!/bin/bash
# scripts/deploy-v2.sh
# Deploy v2 (React + Drupal) to production via git-based workflow
# Usage: ./scripts/deploy-v2.sh [--skip-build]

set -e

SSH_USER="xrdj7j99xhzt"
SSH_HOST="132.148.233.159"
SSH_KEY="${HOME}/.ssh/id_ed25519"
PROD_PATH="/home/${SSH_USER}/public_html"
BRANCH="production"

echo "========================================"
echo "  FAMtastic Designs — v2 Deploy"
echo "========================================"
echo ""

# --- 1. Verify we're on a source branch (not production) ---
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" = "$BRANCH" ]; then
    echo "❌ ERROR: You are on the '$BRANCH' branch."
    echo "   Switch to 'main' or a feature branch first:"
    echo "     git checkout main"
    exit 1
fi

echo "✅ Source branch: $CURRENT_BRANCH"
echo "   Target: $BRANCH"
echo ""

# --- 2. Build frontend ---
if [[ "$1" == "--skip-build" ]]; then
    echo "⚠️  Skipping build (--skip-build passed)"
    if [ ! -d "v2/frontend/dist" ]; then
        echo "❌ ERROR: v2/frontend/dist/ does not exist. Run without --skip-build."
        exit 1
    fi
else
    echo "→ Building v2/frontend..."
    cd v2/frontend
    npm ci
    npm run build
    cd ../..
    echo "✅ Build complete"
fi
echo ""

# --- 3. Switch to production branch, clean old built files ---
echo "→ Switching to '$BRANCH' branch..."
git fetch origin
git checkout "$BRANCH"

echo "→ Removing old built files..."
git rm -rf --ignore-unmatch \
    index.html \
    og-image.jpg \
    admin-content-screenshot.png \
    admin-edit-screenshot.png \
    admin-screenshot.png \
    admin-types-screenshot.png \
    node-preview-screenshot.png \
    spa-screenshot.png \
    assets/ \
    blog/ \
    contact/ \
    faq/ \
    packages/ \
    services/ \
    start/ \
    work/ \
    web/modules/custom/famtastic_pipeline/ \
    web/modules/custom/famtastic_preview/ \
    2>/dev/null || true

echo "✅ Cleaned old files"
echo ""

# --- 4. Copy new built frontend ---
echo "→ Copying built frontend..."
cp v2/frontend/dist/index.html .
cp v2/frontend/dist/og-image.jpg . 2>/dev/null || true

# Copy screenshots if they exist in dist
cp v2/frontend/dist/admin-content-screenshot.png . 2>/dev/null || true
cp v2/frontend/dist/admin-edit-screenshot.png . 2>/dev/null || true
cp v2/frontend/dist/admin-screenshot.png . 2>/dev/null || true
cp v2/frontend/dist/admin-types-screenshot.png . 2>/dev/null || true
cp v2/frontend/dist/node-preview-screenshot.png . 2>/dev/null || true
cp v2/frontend/dist/spa-screenshot.png . 2>/dev/null || true

# Copy assets directory
if [ -d "v2/frontend/dist/assets" ]; then
    cp -r v2/frontend/dist/assets/ .
fi

# Copy route directories (pre-rendered HTML pages)
for dir in blog contact faq packages services start work; do
    if [ -d "v2/frontend/dist/$dir" ]; then
        cp -r "v2/frontend/dist/$dir" .
    fi
done

# --- 5. Copy Drupal custom modules ---
echo "→ Copying Drupal custom modules..."
mkdir -p web/modules/custom
cp -r v2/backend/web/modules/custom/famtastic_pipeline web/modules/custom/
cp -r v2/backend/web/modules/custom/famtastic_preview web/modules/custom/

echo "✅ Files staged"
echo ""

# --- 6. Commit and push ---
echo "→ Committing deploy..."
git add -A
git commit -m "deploy: $(date '+%Y-%m-%d %H:%M:%S')" \
    -m "Source branch: $CURRENT_BRANCH" \
    -m "Commit: $(git rev-parse --short HEAD)" \
    -m "Frontend: v2/frontend/dist/" \
    -m "Backend: v2/backend/web/modules/custom/" || {
    echo "⚠️  No changes to commit (already up to date?)"
}

echo "→ Pushing to origin/$BRANCH..."
git push origin "$BRANCH"
echo "✅ Pushed"
echo ""

# --- 7. Switch back to source branch ---
echo "→ Switching back to $CURRENT_BRANCH..."
git checkout "$CURRENT_BRANCH"
echo ""

# --- 8. Deploy to production server ---
echo "→ Deploying to production server..."
ssh -o StrictHostKeyChecking=no -i "$SSH_KEY" "${SSH_USER}@${SSH_HOST}" "
    set -e
    echo '=== Production Deploy ==='
    cd ${PROD_PATH}
    
    # Ensure we're on production branch
    git fetch origin
    git reset --hard origin/${BRANCH}
    
    # Rebuild Drupal cache
    echo '→ Rebuilding Drupal cache...'
    vendor/bin/drush cr
    
    echo '✅ Production updated'
"

echo ""
echo "========================================"
echo "✅ DEPLOY COMPLETE"
echo "========================================"
echo ""
echo "Smoke tests:"
echo "  curl -I https://famtasticdesigns.com/"
echo "  curl -I https://famtasticdesigns.com/og-image.jpg"
echo "  curl -s https://famtasticdesigns.com/contact/ | grep 'og:title'"
echo ""
