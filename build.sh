#!/bin/bash
# ============================================================
# CN Blog Mailer - Production Build Script (Linux/Mac)
# ============================================================
# This script creates a production-ready ZIP file for WordPress.org
# ============================================================

set -e  # Exit on error

echo ""
echo "============================================================"
echo "  CN Blog Mailer - Production Build Script"
echo "============================================================"
echo ""

# Set plugin name and version
PLUGIN_NAME="cn-blog-mailer"
VERSION="1.0.0"

# Set build directory
BUILD_DIR="build"
PLUGIN_DIR="${BUILD_DIR}/${PLUGIN_NAME}"

# Clean previous build
echo "[1/6] Cleaning previous build..."
rm -rf "${BUILD_DIR}"
mkdir -p "${PLUGIN_DIR}"

echo "[2/6] Installing production dependencies..."
# Install production dependencies only (no dev tools)
composer install --no-dev --optimize-autoloader --no-interaction
if [ $? -ne 0 ]; then
    echo "ERROR: Composer install failed!"
    exit 1
fi

echo "[3/6] Copying plugin files..."
# Copy main plugin files
cp wp-blog-mailer.php "${PLUGIN_DIR}/"
cp readme.txt "${PLUGIN_DIR}/"
cp LICENSE "${PLUGIN_DIR}/"
cp uninstall.php "${PLUGIN_DIR}/"

# Copy directories
echo "    - Copying includes/"
cp -r includes "${PLUGIN_DIR}/"

echo "    - Copying assets/"
cp -r assets "${PLUGIN_DIR}/"

echo "    - Copying templates/"
cp -r templates "${PLUGIN_DIR}/"

echo "    - Copying vendor/ (production only)"
cp -r vendor "${PLUGIN_DIR}/"

echo "[4/6] Removing unnecessary files..."
# Remove development files from the build
rm -rf "${PLUGIN_DIR}/.git"
rm -f "${PLUGIN_DIR}/.gitignore"
rm -rf "${PLUGIN_DIR}/tests"
rm -f "${PLUGIN_DIR}/README.md"
rm -f "${PLUGIN_DIR}/MIGRATION-GUIDE.md"
rm -f "${PLUGIN_DIR}/phpunit.xml"
rm -f "${PLUGIN_DIR}/.phpcs.xml"
rm -f "${PLUGIN_DIR}/composer.json"
rm -f "${PLUGIN_DIR}/composer.lock"
rm -f "${PLUGIN_DIR}/build.bat"
rm -f "${PLUGIN_DIR}/build.sh"

# Remove .DS_Store files (Mac)
find "${PLUGIN_DIR}" -name ".DS_Store" -type f -delete 2>/dev/null || true

# Remove log files
find "${PLUGIN_DIR}" -name "*.log" -type f -delete 2>/dev/null || true

# Remove empty directories
find "${PLUGIN_DIR}" -type d -empty -delete 2>/dev/null || true

echo "[5/6] Creating production ZIP file..."
# Create ZIP file
cd "${BUILD_DIR}"
zip -r "../${PLUGIN_NAME}-v${VERSION}.zip" "${PLUGIN_NAME}" -q
cd ..

echo "[6/6] Verifying ZIP file..."
if [ -f "${PLUGIN_NAME}-v${VERSION}.zip" ]; then
    ZIP_SIZE=$(du -h "${PLUGIN_NAME}-v${VERSION}.zip" | cut -f1)
    echo ""
    echo "============================================================"
    echo "  SUCCESS! Production ZIP created successfully!"
    echo "============================================================"
    echo ""
    echo "  File: ${PLUGIN_NAME}-v${VERSION}.zip"
    echo "  Size: ${ZIP_SIZE}"
    echo "  Location: $(pwd)/${PLUGIN_NAME}-v${VERSION}.zip"
    echo ""
    echo "  This ZIP is ready for WordPress.org submission!"
    echo ""
    echo "  Build directory: ${BUILD_DIR}/${PLUGIN_NAME}/"
    echo "  (You can review the contents before submission)"
    echo ""
    echo "============================================================"
else
    echo "ERROR: ZIP file was not created!"
    exit 1
fi

echo ""
echo "NOTE: Don't forget to restore dev dependencies after building:"
echo "  composer install"
echo ""
