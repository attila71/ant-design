<?php
/**
 * Script di installazione per Stabilimento Balneare
 * 
 * Questo script automatizza l'installazione dell'applicazione:
 * - Controlla i requisiti di sistema
 * - Testa la connessione al database
 * - Crea le tabelle se necessario
 * - Inserisce i dati di esempio
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Funzione per controllare i requisiti
function checkRequirements() {
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0') >= 0,
        'PDO Extension' => extension_loaded('pdo'),
        'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
        'JSON Extension' => extension_loaded('json'),
        'Config Directory Writable' => is_writable(__DIR__ . '/config'),
    ];
    
    return $requirements;
}

// Funzione per testare la connessione al database
function testDatabaseConnection($host, $database, $username, $password) {
    try {
        $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return true;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}

// Funzione per creare il file di configurazione
function createConfigFile($host, $database, $username, $password) {
    $configContent = "<?php
// Configurazione database MariaDB
class Database {
    private \$host = '$host';
    private \$database = '$database';
    private \$username = '$username';
    private \$password = '$password';
    private \$charset = 'utf8mb4';
    private \$pdo;

    public function __construct(\$host = null, \$username = null, \$password = null) {
        if (\$host) \$this->host = \$host;
        if (\$username) \$this->username = \$username;
        if (\$password) \$this->password = \$password;
    }

    public function connect() {
        if (\$this->pdo === null) {
            try {
                \$dsn = \"mysql:host={\$this->host};dbname={\$this->database};charset={\$this->charset}\";
                \$options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                \$this->pdo = new PDO(\$dsn, \$this->username, \$this->password, \$options);
            } catch (PDOException \$e) {
                throw new Exception(\"Errore connessione database: \" . \$e->getMessage());
            }
        }
        return \$this->pdo;
    }

    public function query(\$sql, \$params = []) {
        \$stmt = \$this->connect()->prepare(\$sql);
        \$stmt->execute(\$params);
        return \$stmt;
    }

    public function fetch(\$sql, \$params = []) {
        return \$this->query(\$sql, \$params)->fetch();
    }

    public function fetchAll(\$sql, \$params = []) {
        return \$this->query(\$sql, \$params)->fetchAll();
    }

    public function lastInsertId() {
        return \$this->connect()->lastInsertId();
    }

    public function beginTransaction() {
        return \$this->connect()->beginTransaction();
    }

    public function commit() {
        return \$this->connect()->commit();
    }

    public function rollback() {
        return \$this->connect()->rollback();
    }
}
?>";

    return file_put_contents(__DIR__ . '/config/database.php', $configContent);
}

// Funzione per installare il database
function installDatabase($host, $database, $username, $password) {
    try {
        $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Leggi e esegui lo schema SQL
        $schemaFile = __DIR__ . '/database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception('File schema.sql non trovato');
        }

        $schema = file_get_contents($schemaFile);
        
        // Rimuovi il comando USE database dal file
        $schema = preg_replace('/USE\s+\w+;/', '', $schema);
        
        // Esegui ogni statement SQL
        $statements = explode(';', $schema);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }

        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

// Gestione POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 2) {
        $host = $_POST['host'] ?? 'localhost';
        $database = $_POST['database'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($database) || empty($username)) {
            $error = 'Database e username sono obbligatori';
        } else {
            $testResult = testDatabaseConnection($host, $database, $username, $password);
            if ($testResult === true) {
                // Salva configurazione in sessione
                session_start();
                $_SESSION['db_config'] = compact('host', 'database', 'username', 'password');
                $success = 'Connessione al database riuscita!';
                $step = 3;
            } else {
                $error = "Errore connessione database: $testResult";
            }
        }
    } elseif ($step == 3) {
        session_start();
        $config = $_SESSION['db_config'] ?? null;
        
        if (!$config) {
            $error = 'Configurazione database persa. Riprova dal passo 2.';
            $step = 2;
        } else {
            // Crea file di configurazione
            if (createConfigFile($config['host'], $config['database'], $config['username'], $config['password'])) {
                // Installa database
                $installResult = installDatabase($config['host'], $config['database'], $config['username'], $config['password']);
                if ($installResult === true) {
                    $success = 'Installazione completata con successo!';
                    $step = 4;
                    // Pulisci sessione
                    unset($_SESSION['db_config']);
                } else {
                    $error = "Errore installazione database: $installResult";
                }
            } else {
                $error = 'Impossibile creare il file di configurazione. Controlla i permessi.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installazione Stabilimento Balneare</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; background: #f8fafc; color: #1e293b; }
        .container { max-width: 800px; margin: 2rem auto; padding: 2rem; }
        .card { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); padding: 2rem; margin-bottom: 2rem; }
        .header { text-align: center; margin-bottom: 2rem; }
        .header h1 { color: #2563eb; margin-bottom: 0.5rem; }
        .steps { display: flex; justify-content: space-between; margin-bottom: 2rem; }
        .step { padding: 1rem; border-radius: 8px; text-align: center; flex: 1; margin: 0 0.5rem; }
        .step.active { background: #2563eb; color: white; }
        .step.completed { background: #10b981; color: white; }
        .step.pending { background: #e2e8f0; color: #64748b; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-success { background: #10b981; color: white; }
        .alert { padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .requirements { list-style: none; }
        .requirements li { padding: 0.5rem 0; display: flex; justify-content: space-between; }
        .check { color: #16a34a; }
        .cross { color: #dc2626; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏖️ Installazione Stabilimento Balneare</h1>
            <p>Configurazione guidata dell'applicazione</p>
        </div>

        <div class="steps">
            <div class="step <?= $step >= 1 ? ($step == 1 ? 'active' : 'completed') : 'pending' ?>">
                1. Requisiti
            </div>
            <div class="step <?= $step >= 2 ? ($step == 2 ? 'active' : 'completed') : 'pending' ?>">
                2. Database
            </div>
            <div class="step <?= $step >= 3 ? ($step == 3 ? 'active' : 'completed') : 'pending' ?>">
                3. Installazione
            </div>
            <div class="step <?= $step >= 4 ? ($step == 4 ? 'active' : 'completed') : 'pending' ?>">
                4. Completato
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <div class="card">
                <h2>Controllo Requisiti di Sistema</h2>
                <ul class="requirements">
                    <?php foreach (checkRequirements() as $requirement => $passed): ?>
                        <li>
                            <span><?= $requirement ?></span>
                            <span class="<?= $passed ? 'check' : 'cross' ?>">
                                <?= $passed ? '✓' : '✗' ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <?php if (array_reduce(checkRequirements(), function($carry, $item) { return $carry && $item; }, true)): ?>
                    <div style="margin-top: 2rem;">
                        <a href="?step=2" class="btn btn-primary">Continua →</a>
                    </div>
                <?php else: ?>
                    <div style="margin-top: 2rem;">
                        <p style="color: #dc2626;">Risolvi i problemi di requisiti prima di continuare.</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($step == 2): ?>
            <div class="card">
                <h2>Configurazione Database</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Host Database</label>
                        <input type="text" name="host" value="localhost" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nome Database</label>
                        <input type="text" name="database" value="stabilimento_balneare" required>
                        <small>Il database deve già esistere</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Testa Connessione</button>
                </form>
            </div>

        <?php elseif ($step == 3): ?>
            <div class="card">
                <h2>Installazione Database</h2>
                <p>Clicca per creare le tabelle e inserire i dati di esempio.</p>
                
                <form method="POST">
                    <button type="submit" class="btn btn-primary">Installa Database</button>
                </form>
            </div>

        <?php elseif ($step == 4): ?>
            <div class="card text-center">
                <h2>✅ Installazione Completata!</h2>
                <p>L'applicazione è stata installata con successo.</p>
                
                <div style="margin: 2rem 0;">
                    <h3>Prossimi Passi:</h3>
                    <ol style="text-align: left; display: inline-block;">
                        <li>Elimina questo file (install.php) per sicurezza</li>
                        <li>Configura il web server (vedi README.md)</li>
                        <li>Accedi all'applicazione</li>
                    </ol>
                </div>
                
                <a href="frontend/index.html" class="btn btn-success">Vai all'Applicazione</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>