<?php

class Database
{
    private ?PDO $connection = null;

    public function connect(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $databaseUrl = env('DATABASE_URL');

        if (!empty($databaseUrl)) {
            $parts = parse_url($databaseUrl);

            if ($parts === false || empty($parts['host'])) {
                if (preg_match('#^postgres(?:ql)?://([^:]+):(.*)@([^:/]+)(?::(\d+))?/([^?]+)(?:\?(.*))?$#', $databaseUrl, $m)) {
                    $parts = [
                        'user' => $m[1],
                        'pass' => $m[2],
                        'host' => $m[3],
                        'port' => !empty($m[4]) ? $m[4] : '5432',
                        'path' => $m[5],
                        'query' => $m[6] ?? ''
                    ];
                }
            }

            $host = $parts['host'] ?? 'localhost';
            $port = (string)($parts['port'] ?? '5432');
            $dbName = ltrim($parts['path'] ?? 'postgres', '/');
            $username = isset($parts['user']) ? urldecode($parts['user']) : 'postgres';
            $password = isset($parts['pass']) ? urldecode($parts['pass']) : '';

            $sslmode = 'require';
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
                if (isset($query['sslmode'])) {
                    $sslmode = $query['sslmode'];
                }
            }

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbName};sslmode={$sslmode}";
        } else {
            $host = env('DB_HOST', 'localhost');
            $port = env('DB_PORT', '5432');
            $dbName = env('DB_NAME', 'plateforme_taxi');
            $username = env('DB_USER', 'postgres');
            $password = env('DB_PASSWORD', '');
            $defaultSsl = ($host === 'localhost' || $host === '127.0.0.1') ? 'prefer' : 'require';
            $sslmode = env('DB_SSLMODE', $defaultSsl);

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbName};sslmode={$sslmode}";
        }

        try {
            $this->connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            return $this->connection;

        } catch (PDOException $e) {

            error_log('Erreur connexion DB : ' . $e->getMessage());

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Erreur de connexion à la base de données.'
            ]);

            exit;
        }
    }
}
