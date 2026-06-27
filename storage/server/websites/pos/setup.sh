#!/data/data/com.termux/files/usr/bin/bash
# Setup POS website for AHPL

AHPL_DIR="$HOME/ahpl-server"
POS_DIR="$AHPL_DIR/storage/server/websites/pos"

echo "=== POS AHPL Setup ==="

# 1. Edit database config
echo ""
echo "Edit database name in config.php"
echo "Currently set to: pos"
echo "Change if you used a different database name:"
echo "  nano $POS_DIR/config.php"
echo "  -> change DB_NAME to your database name"

# 2. Create symlink so /websites/pos/ is accessible
if [ ! -L "$AHPL_DIR/websites" ]; then
    echo ""
    echo "Creating symlink: ln -s storage/server/websites websites"
    ln -s "$AHPL_DIR/storage/server/websites" "$AHPL_DIR/websites"
    echo "Done!"
else
    echo "Symlink already exists"
fi

# 3. Register in panel database
echo ""
echo "Registering website in panel..."
php -r "
\$db = new SQLite3('$AHPL_DIR/storage/server/database/ahpl.db');
\$db->exec(\"INSERT OR IGNORE INTO websites (name, folder, created_at) VALUES ('POS AHPL', 'pos', datetime('now'))\");
echo 'POS website registered!\n';
"

# 4. Create default admin if not exists
echo ""
echo "Checking admin user..."
php -r "
require_once '$POS_DIR/config.php';
\$pdo = getDB();
\$count = \$pdo->query(\"SELECT COUNT(*) FROM users\")->fetchColumn();
if (\$count == 0) {
    \$pdo->prepare(\"INSERT INTO users (name, username, password, role, status, created_at, updated_at) VALUES ('Admin', 'admin', :pw, 'admin', 1, NOW(), NOW())\")->execute([':pw' => password_hash('admin', PASSWORD_DEFAULT)]);
    echo 'Default admin created (admin/admin)' . PHP_EOL;
} else {
    echo 'Users already exist' . PHP_EOL;
}
"

echo ""
echo "=== Done! ==="
echo "Access: http://localhost:8080/websites/pos/"
echo "Default login: admin / admin"
