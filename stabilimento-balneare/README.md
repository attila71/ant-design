# 🏖️ Gestione Stabilimento Balneare

Un'applicazione web completa per la gestione delle prenotazioni di uno stabilimento balneare, sviluppata in PHP con frontend moderno e funzionalità drag & drop.

## ✨ Caratteristiche

- **Dashboard** con statistiche in tempo reale
- **Gestione clienti** con anagrafe completa
- **Prenotazioni** con controllo disponibilità
- **Mappa interattiva** delle tende balneari con drag & drop
- **Conti aperti** per spese aggiuntive
- **Design responsive** moderno
- **API REST** per tutte le operazioni

## 🛠️ Tecnologie

- **Backend**: PHP 7.4+, MariaDB/MySQL
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Database**: MariaDB con schema completo
- **UI**: Design moderno con Font Awesome e Google Fonts

## 📋 Requisiti

- PHP 7.4 o superiore
- MariaDB 10.3+ o MySQL 5.7+
- Web server (Apache/Nginx)
- Browser moderno con supporto ES6

## 🚀 Installazione

### 1. Configurazione Database

```bash
# Accedi a MariaDB
mysql -u root -p

# Crea il database e l'utente
CREATE DATABASE stabilimento_balneare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'stabilimento'@'localhost' IDENTIFIED BY 'password_sicura';
GRANT ALL PRIVILEGES ON stabilimento_balneare.* TO 'stabilimento'@'localhost';
FLUSH PRIVILEGES;

# Importa lo schema
mysql -u stabilimento -p stabilimento_balneare < database/schema.sql
```

### 2. Configurazione PHP

Modifica il file `config/database.php` con le tue credenziali:

```php
private $host = 'localhost';
private $database = 'stabilimento_balneare';
private $username = 'stabilimento';
private $password = 'password_sicura';
```

### 3. Configurazione Web Server

#### Apache

Crea un virtual host:

```apache
<VirtualHost *:80>
    ServerName stabilimento.local
    DocumentRoot /path/to/stabilimento-balneare/frontend
    
    # Rewrite per API
    RewriteEngine On
    RewriteRule ^api/(.*)$ ../backend/api.php?path=$1 [QSA,L]
    
    <Directory "/path/to/stabilimento-balneare/frontend">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name stabilimento.local;
    root /path/to/stabilimento-balneare/frontend;
    index index.html;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ /api/(.*)$ {
        fastcgi_pass php-fpm;
        fastcgi_param SCRIPT_FILENAME /path/to/stabilimento-balneare/backend/api.php;
        fastcgi_param QUERY_STRING path=$1&$query_string;
        include fastcgi_params;
    }
}
```

### 4. Permessi File

```bash
# Imposta i permessi corretti
chmod -R 755 stabilimento-balneare/
chmod -R 644 stabilimento-balneare/config/
```

## 🎯 Utilizzo

### Dashboard
- Visualizza statistiche in tempo reale
- Panoramica prenotazioni recenti
- Conti aperti non pagati

### Gestione Tende
- **Mappa interattiva** con le tende
- **Drag & drop** per riposizionare le tende
- **Codice colore**: Verde (libera), Rosso (occupata), Giallo (prenotata)
- Filtro per data per vedere lo stato in date specifiche

### Prenotazioni
- Crea nuove prenotazioni con controllo disponibilità
- Gestisci date di inizio e fine
- Sistema di acconti e note
- Storico modifiche automatico

### Clienti
- Anagrafe completa con ricerca
- Telefono e email di contatto
- Creazione rapida di prenotazioni per cliente esistente

### Conti Aperti
- Aggiungi spese extra alle prenotazioni
- Servizi predefiniti (colazione, pranzo, etc.)
- Segna come pagato
- Integrazione con dashboard

## 🔧 API Endpoints

### Clienti
- `GET /api/clienti` - Lista clienti
- `POST /api/clienti` - Crea cliente
- `GET /api/clienti/search?term=xyz` - Cerca clienti

### Tende
- `GET /api/tende` - Lista tende con stato
- `PUT /api/tende` - Aggiorna posizione (drag & drop)

### Prenotazioni
- `GET /api/prenotazioni` - Lista prenotazioni
- `POST /api/prenotazioni` - Crea prenotazione
- `PUT /api/prenotazioni` - Modifica prenotazione
- `DELETE /api/prenotazioni` - Annulla prenotazione

### Conti
- `GET /api/conti` - Lista conti aperti
- `POST /api/conti` - Aggiungi spesa
- `PUT /api/conti` - Segna come pagato

### Statistiche
- `GET /api/stats` - Statistiche dashboard

## 🎨 Personalizzazione

### Colori e Tema

Modifica le variabili CSS in `frontend/css/style.css`:

```css
:root {
    --primary-color: #2563eb;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
}
```

### Servizi Extra

Modifica la tabella `servizi_extra` nel database per personalizzare i servizi offerti.

### Tende Balneari

Aggiungi/modifica tende nella tabella `tende_balneari` con posizioni personalizzate.

## 🔒 Sicurezza

- **Validazione input** su tutti i form
- **Prepared statements** per prevenire SQL injection
- **CORS headers** configurati
- **Sanitizzazione** output HTML

## 📱 Responsive Design

L'applicazione è completamente responsive e ottimizzata per:
- Desktop (1200px+)
- Tablet (768px - 1199px)
- Mobile (fino a 767px)

## 🐛 Debugging

### Log degli errori

Attiva i log di PHP:

```php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
```

### Console Browser

Apri la console per vedere eventuali errori JavaScript.

### Database

Controlla i log di MariaDB:

```bash
tail -f /var/log/mysql/error.log
```

## 📈 Performance

### Ottimizzazioni suggerite

1. **Cache delle query** frequenti
2. **Compressione** assets CSS/JS
3. **CDN** per Font Awesome e Google Fonts
4. **Indexing** database ottimizzato

## 🔄 Backup

### Database

```bash
# Backup giornaliero
mysqldump -u stabilimento -p stabilimento_balneare > backup_$(date +%Y%m%d).sql

# Restore
mysql -u stabilimento -p stabilimento_balneare < backup_YYYYMMDD.sql
```

### File

Backup regolare della directory dell'applicazione.

## 🤝 Contributi

1. Fork del repository
2. Crea un branch per le nuove funzionalità
3. Commit delle modifiche
4. Push al branch
5. Crea una Pull Request

## 📄 Licenza

Questo progetto è open source. Sentiti libero di modificarlo secondo le tue esigenze.

## 🆘 Supporto

Per problemi o domande:
1. Controlla la documentazione
2. Verifica i log di errore
3. Controlla la configurazione del database
4. Verifica i permessi dei file

---

**Buona gestione del tuo stabilimento balneare! 🏖️**