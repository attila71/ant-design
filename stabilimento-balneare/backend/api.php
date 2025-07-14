<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? $_GET['path'] : '';
$input = json_decode(file_get_contents('php://input'), true);

function response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function error($message, $status = 400) {
    response(['error' => $message], $status);
}

try {
    switch ($path) {
        // === CLIENTI ===
        case 'clienti':
            if ($method === 'GET') {
                $clienti = $db->fetchAll("SELECT * FROM clienti ORDER BY nome");
                response($clienti);
            } elseif ($method === 'POST') {
                if (!$input['nome'] || !$input['telefono']) {
                    error('Nome e telefono sono obbligatori');
                }
                $sql = "INSERT INTO clienti (nome, telefono, email, note) VALUES (?, ?, ?, ?)";
                $db->query($sql, [$input['nome'], $input['telefono'], $input['email'] ?? '', $input['note'] ?? '']);
                $id = $db->lastInsertId();
                $cliente = $db->fetch("SELECT * FROM clienti WHERE id = ?", [$id]);
                response($cliente, 201);
            }
            break;

        case 'clienti/search':
            if ($method === 'GET') {
                $term = $_GET['term'] ?? '';
                $sql = "SELECT * FROM clienti WHERE nome LIKE ? OR telefono LIKE ? ORDER BY nome";
                $clienti = $db->fetchAll($sql, ["%$term%", "%$term%"]);
                response($clienti);
            }
            break;

        // === TENDE BALNEARI ===
        case 'tende':
            if ($method === 'GET') {
                $tende = $db->fetchAll("
                    SELECT t.*, 
                           CASE WHEN p.id IS NOT NULL THEN 'occupata' ELSE 'libera' END as stato_attuale,
                           p.data_inizio, p.data_fine, c.nome as cliente_nome, c.telefono as cliente_telefono
                    FROM tende_balneari t
                    LEFT JOIN prenotazioni p ON t.id = p.tenda_id 
                        AND CURDATE() BETWEEN p.data_inizio AND p.data_fine 
                        AND p.stato = 'confermata'
                    LEFT JOIN clienti c ON p.cliente_id = c.id
                    WHERE t.attiva = 1
                    ORDER BY t.numero_tenda
                ");
                response($tende);
            } elseif ($method === 'PUT') {
                // Aggiorna posizione tenda (drag & drop)
                if (!isset($input['id']) || !isset($input['posizione_x']) || !isset($input['posizione_y'])) {
                    error('ID tenda e posizioni sono obbligatori');
                }
                $sql = "UPDATE tende_balneari SET posizione_x = ?, posizione_y = ? WHERE id = ?";
                $db->query($sql, [$input['posizione_x'], $input['posizione_y'], $input['id']]);
                response(['success' => true]);
            }
            break;

        // === PRENOTAZIONI ===
        case 'prenotazioni':
            if ($method === 'GET') {
                $data_inizio = $_GET['data_inizio'] ?? date('Y-m-d');
                $data_fine = $_GET['data_fine'] ?? date('Y-m-d', strtotime('+30 days'));
                
                $sql = "
                    SELECT p.*, c.nome as cliente_nome, c.telefono as cliente_telefono, 
                           t.numero_tenda, t.zona, t.tipo, t.posizione_x, t.posizione_y
                    FROM prenotazioni p
                    JOIN clienti c ON p.cliente_id = c.id
                    JOIN tende_balneari t ON p.tenda_id = t.id
                    WHERE p.data_inizio <= ? AND p.data_fine >= ?
                    ORDER BY p.data_inizio, t.numero_tenda
                ";
                $prenotazioni = $db->fetchAll($sql, [$data_fine, $data_inizio]);
                response($prenotazioni);
            } elseif ($method === 'POST') {
                if (!$input['cliente_id'] || !$input['tenda_id'] || !$input['data_inizio'] || !$input['data_fine']) {
                    error('Cliente, tenda e date sono obbligatori');
                }

                // Verifica disponibilità
                $conflitti = $db->fetchAll("
                    SELECT id FROM prenotazioni 
                    WHERE tenda_id = ? AND stato = 'confermata'
                    AND ((data_inizio <= ? AND data_fine >= ?) 
                         OR (data_inizio <= ? AND data_fine >= ?)
                         OR (data_inizio >= ? AND data_fine <= ?))
                ", [
                    $input['tenda_id'], 
                    $input['data_inizio'], $input['data_inizio'],
                    $input['data_fine'], $input['data_fine'],
                    $input['data_inizio'], $input['data_fine']
                ]);

                if (!empty($conflitti)) {
                    error('Tenda già prenotata nel periodo selezionato');
                }

                $db->beginTransaction();
                try {
                    $sql = "INSERT INTO prenotazioni (cliente_id, tenda_id, data_inizio, data_fine, stato, prezzo_totale, acconto, note) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $db->query($sql, [
                        $input['cliente_id'], $input['tenda_id'], $input['data_inizio'], $input['data_fine'],
                        $input['stato'] ?? 'confermata', $input['prezzo_totale'], 
                        $input['acconto'] ?? 0, $input['note'] ?? ''
                    ]);
                    $id = $db->lastInsertId();

                    // Storico
                    $db->query("INSERT INTO storico_prenotazioni (prenotazione_id, azione, dati_nuovi) VALUES (?, 'creata', ?)", 
                               [$id, json_encode($input)]);
                    
                    $db->commit();
                    $prenotazione = $db->fetch("
                        SELECT p.*, c.nome as cliente_nome, c.telefono as cliente_telefono, 
                               t.numero_tenda, t.zona, t.tipo
                        FROM prenotazioni p
                        JOIN clienti c ON p.cliente_id = c.id
                        JOIN tende_balneari t ON p.tenda_id = t.id
                        WHERE p.id = ?
                    ", [$id]);
                    response($prenotazione, 201);
                } catch (Exception $e) {
                    $db->rollback();
                    error('Errore durante la creazione della prenotazione');
                }
            } elseif ($method === 'PUT') {
                if (!isset($input['id'])) {
                    error('ID prenotazione obbligatorio');
                }

                $prenotazione_old = $db->fetch("SELECT * FROM prenotazioni WHERE id = ?", [$input['id']]);
                if (!$prenotazione_old) {
                    error('Prenotazione non trovata', 404);
                }

                $db->beginTransaction();
                try {
                    $fields = [];
                    $values = [];
                    
                    foreach (['tenda_id', 'data_inizio', 'data_fine', 'stato', 'prezzo_totale', 'acconto', 'note'] as $field) {
                        if (isset($input[$field])) {
                            $fields[] = "$field = ?";
                            $values[] = $input[$field];
                        }
                    }
                    
                    if (!empty($fields)) {
                        $values[] = $input['id'];
                        $sql = "UPDATE prenotazioni SET " . implode(', ', $fields) . " WHERE id = ?";
                        $db->query($sql, $values);

                        // Storico
                        $azione = isset($input['tenda_id']) ? 'spostata' : 'modificata';
                        $db->query("INSERT INTO storico_prenotazioni (prenotazione_id, azione, dati_precedenti, dati_nuovi) VALUES (?, ?, ?, ?)", 
                                   [$input['id'], $azione, json_encode($prenotazione_old), json_encode($input)]);
                    }
                    
                    $db->commit();
                    response(['success' => true]);
                } catch (Exception $e) {
                    $db->rollback();
                    error('Errore durante l\'aggiornamento della prenotazione');
                }
            } elseif ($method === 'DELETE') {
                $id = $_GET['id'] ?? null;
                if (!$id) error('ID prenotazione obbligatorio');

                $prenotazione = $db->fetch("SELECT * FROM prenotazioni WHERE id = ?", [$id]);
                if (!$prenotazione) error('Prenotazione non trovata', 404);

                $db->beginTransaction();
                try {
                    $db->query("INSERT INTO storico_prenotazioni (prenotazione_id, azione, dati_precedenti) VALUES (?, 'annullata', ?)", 
                               [$id, json_encode($prenotazione)]);
                    $db->query("UPDATE prenotazioni SET stato = 'annullata' WHERE id = ?", [$id]);
                    $db->commit();
                    response(['success' => true]);
                } catch (Exception $e) {
                    $db->rollback();
                    error('Errore durante l\'annullamento della prenotazione');
                }
            }
            break;

        // === CONTI APERTI ===
        case 'conti':
            if ($method === 'GET') {
                $prenotazione_id = $_GET['prenotazione_id'] ?? null;
                if ($prenotazione_id) {
                    $conti = $db->fetchAll("
                        SELECT ca.*, s.nome as servizio_nome 
                        FROM conti_aperti ca
                        LEFT JOIN servizi_extra s ON ca.descrizione = s.nome
                        WHERE ca.prenotazione_id = ? 
                        ORDER BY ca.data_inserimento
                    ", [$prenotazione_id]);
                } else {
                    $conti = $db->fetchAll("
                        SELECT ca.*, p.id as prenotazione_id, c.nome as cliente_nome, t.numero_tenda
                        FROM conti_aperti ca
                        JOIN prenotazioni p ON ca.prenotazione_id = p.id
                        JOIN clienti c ON p.cliente_id = c.id
                        JOIN tende_balneari t ON p.tenda_id = t.id
                        WHERE ca.pagato = 0
                        ORDER BY ca.data_inserimento DESC
                    ");
                }
                response($conti);
            } elseif ($method === 'POST') {
                if (!$input['prenotazione_id'] || !$input['descrizione'] || !$input['importo']) {
                    error('Prenotazione, descrizione e importo sono obbligatori');
                }
                $sql = "INSERT INTO conti_aperti (prenotazione_id, descrizione, importo, note) VALUES (?, ?, ?, ?)";
                $db->query($sql, [$input['prenotazione_id'], $input['descrizione'], $input['importo'], $input['note'] ?? '']);
                $id = $db->lastInsertId();
                $conto = $db->fetch("SELECT * FROM conti_aperti WHERE id = ?", [$id]);
                response($conto, 201);
            } elseif ($method === 'PUT') {
                if (!isset($input['id'])) {
                    error('ID conto obbligatorio');
                }
                $sql = "UPDATE conti_aperti SET pagato = ? WHERE id = ?";
                $db->query($sql, [$input['pagato'] ? 1 : 0, $input['id']]);
                response(['success' => true]);
            }
            break;

        // === SERVIZI EXTRA ===
        case 'servizi':
            if ($method === 'GET') {
                $servizi = $db->fetchAll("SELECT * FROM servizi_extra WHERE attivo = 1 ORDER BY nome");
                response($servizi);
            }
            break;

        // === STATISTICHE ===
        case 'stats':
            if ($method === 'GET') {
                $oggi = date('Y-m-d');
                $stats = [
                    'prenotazioni_oggi' => $db->fetch("SELECT COUNT(*) as count FROM prenotazioni WHERE ? BETWEEN data_inizio AND data_fine AND stato = 'confermata'", [$oggi])['count'],
                    'tende_occupate' => $db->fetch("SELECT COUNT(*) as count FROM prenotazioni p JOIN tende_balneari t ON p.tenda_id = t.id WHERE ? BETWEEN p.data_inizio AND p.data_fine AND p.stato = 'confermata'", [$oggi])['count'],
                    'tende_totali' => $db->fetch("SELECT COUNT(*) as count FROM tende_balneari WHERE attiva = 1")['count'],
                    'fatturato_mese' => $db->fetch("SELECT COALESCE(SUM(prezzo_totale), 0) as totale FROM prenotazioni WHERE MONTH(data_inizio) = MONTH(?) AND YEAR(data_inizio) = YEAR(?) AND stato = 'confermata'", [$oggi, $oggi])['totale'],
                    'conti_aperti' => $db->fetch("SELECT COALESCE(SUM(importo), 0) as totale FROM conti_aperti WHERE pagato = 0")['totale']
                ];
                response($stats);
            }
            break;

        default:
            error('Endpoint non trovato', 404);
    }

} catch (Exception $e) {
    error('Errore del server: ' . $e->getMessage(), 500);
}
?>