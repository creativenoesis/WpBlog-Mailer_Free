# CN Blog Mailer - Build Instructions

This document explains how to create a production-ready ZIP file for WordPress.org submission.

## 🎯 Quick Start

### Windows
```batch
build.bat
```

### Linux/Mac
```bash
chmod +x build.sh
./build.sh
```

## 📦 What the Build Script Does

The build script performs the following steps:

1. **Cleans Previous Build** - Removes any old build directory
2. **Installs Production Dependencies** - Runs `composer install --no-dev --optimize-autoloader`
   - Removes development tools (PHPUnit, WPCS)
   - Optimizes autoloader for production
   - Keeps only required dependencies (Action Scheduler)
3. **Copies Plugin Files** - Copies all necessary files to build directory
4. **Removes Development Files** - Excludes:
   - `.git/` directory
   - `README.md` (developer documentation)
   - `MIGRATION-GUIDE.md`
   - `tests/` directory
   - `composer.json` and `composer.lock`
   - Build scripts themselves
   - Log files
   - `.DS_Store` files
5. **Creates ZIP File** - Creates `cn-blog-mailer-v1.0.0.zip`
6. **Verifies Output** - Confirms the ZIP was created successfully

## 📋 Files Included in Production ZIP

### ✅ Included
- `wp-blog-mailer.php` (main plugin file)
- `readme.txt` (WordPress.org readme)
- `LICENSE`
- `uninstall.php`
- `assets/` (CSS, JS, images)
- `includes/` (all PHP code)
- `templates/` (email templates)
- `vendor/` (production dependencies only)

### ❌ Excluded
- `.git/` (version control)
- `README.md` (developer docs)
- `MIGRATION-GUIDE.md`
- `tests/` (PHPUnit tests)
- `composer.json`, `composer.lock`
- `phpunit.xml`, `.phpcs.xml`
- `build.bat`, `build.sh`
- All `.log` files
- All `.DS_Store` files

## 🔧 Before Building

Make sure you have:
- Composer installed
- All changes committed to Git
- Updated version number in:
  - `wp-blog-mailer.php` (header comment)
  - `readme.txt` (Stable tag)
  - `build.bat` and `build.sh` (VERSION variable, if different)

## 📤 After Building

1. **Verify the ZIP** - Check `build/cn-blog-mailer/` directory contents
2. **Test Installation** - Install the ZIP on a clean WordPress site
3. **Restore Dev Dependencies** - Run `composer install` to restore development tools
4. **Submit to WordPress.org** - Upload `cn-blog-mailer-v1.0.0.zip`

## ⚠️ Important Notes

### Why `--no-dev`?
The `composer install --no-dev` flag removes development dependencies:
- **PHPUnit** (~5MB) - Testing framework (not needed in production)
- **WordPress Coding Standards** (~2MB) - Code quality tools (not needed in production)

This reduces plugin size by ~7MB and improves performance.

### Restoring Dev Dependencies
After building, restore development tools for continued development:
```bash
composer install
```

This will reinstall:
- `phpunit/phpunit` - For running tests
- `wp-coding-standards/wpcs` - For code quality checks

## 🚀 WordPress.org Submission Checklist

Before uploading to WordPress.org:

- [ ] Run build script successfully
- [ ] Verify ZIP file was created
- [ ] Extract and review build directory contents
- [ ] Test installation on clean WordPress site
- [ ] Verify all features work
- [ ] Check that no dev files are included
- [ ] Confirm version numbers match everywhere
- [ ] Review readme.txt one final time
- [ ] Prepare plugin assets (banner, icon, screenshots)

## 📊 Expected ZIP Size

**Production ZIP** (with `--no-dev`): ~2-3 MB
**With dev dependencies**: ~9-10 MB

## 🐛 Troubleshooting

### Windows: "Compress-Archive command not found"
Install PowerShell 5.0+ or manually create ZIP using:
- 7-Zip: Right-click build folder → 7-Zip → Add to archive
- WinRAR: Right-click build folder → Add to archive

### Linux/Mac: "zip command not found"
Install zip utility:
```bash
# Ubuntu/Debian
sudo apt-get install zip

# Mac (Homebrew)
brew install zip
```

### Composer errors
Make sure Composer is installed and in your PATH:
```bash
composer --version
```

## 📝 Version History

- **1.0.0** - Initial release build process

## 🆘 Support

For build issues, check:
1. Composer is installed: `composer --version`
2. PHP version is 7.4+: `php --version`
3. All file permissions are correct
4. No syntax errors in PHP files

---

**Happy Building! 🎉**
