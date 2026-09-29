<?php

class AuthController
{
    private Admin $adminModel;

    public function __construct(Admin $adminModel)
    {
        $this->adminModel = $adminModel;
    }

    public function login(): never
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            Response::error('Le corps de la requête doit être un JSON valide.', null, 400);
        }

        $email = trim($input['email'] ?? '');
        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '') {
            Response::error(
                'Email et mot de passe sont obligatoires.',
                [
                    'email' => $email === '' ? 'Champ requis.' : null,
                    'password' => $password === '' ? 'Champ requis.' : null
                ],
                422
            );
        }

        $admin = $this->adminModel->findByEmail($email);

        if ($admin === null || !password_verify($password, $admin['password'])) {
            Response::error('Identifiants incorrects.', null, 401);
        }

        $token = Jwt::encode([
            'sub' => $admin['id'],
            'email' => $admin['email']
        ]);

        Response::success('Connexion réussie.', [
            'token' => $token,
            'admin' => [
                'id' => $admin['id'],
                'nom' => $admin['nom'],
                'prenom' => $admin['prenom'],
                'email' => $admin['email']
            ]
        ]);
    }

    public function register(): never
    {
        $inviteCode = (string) env('ADMIN_INVITE_CODE', '');

        if ($inviteCode === '') {
            Response::error('Les inscriptions administrateur sont désactivées.', null, 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            Response::error('Le corps de la requête doit être un JSON valide.', null, 400);
        }

        $nom = trim((string) ($input['nom'] ?? ''));
        $prenom = trim((string) ($input['prenom'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? $password);
        $code = (string) ($input['invite_code'] ?? '');

        $errors = [];

        if ($nom === '') {
            $errors['nom'] = 'Champ requis.';
        }

        if ($prenom === '') {
            $errors['prenom'] = 'Champ requis.';
        }

        if ($email === '') {
            $errors['email'] = 'Champ requis.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "L'email n'est pas valide.";
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Le mot de passe doit faire au moins 8 caractères.';
        } elseif ($password !== $confirmation) {
            $errors['password_confirmation'] = 'Les mots de passe ne correspondent pas.';
        }

        if ($errors !== []) {
            Response::error('Données invalides.', $errors, 422);
        }

        if (!hash_equals($inviteCode, $code)) {
            Response::error("Code d'invitation incorrect.", null, 403);
        }

        if ($this->adminModel->findByEmail($email) !== null) {
            Response::error('Un compte existe déjà avec cet email.', ['email' => 'Email déjà utilisé.'], 409);
        }

        try {
            $id = $this->adminModel->create(
                $nom,
                $prenom,
                $email,
                password_hash($password, PASSWORD_BCRYPT)
            );
        } catch (PDOException $e) {
            // 23505 = violation de contrainte d'unicité (inscription simultanée)
            if ($e->getCode() === '23505') {
                Response::error('Un compte existe déjà avec cet email.', ['email' => 'Email déjà utilisé.'], 409);
            }

            throw $e;
        }

        $token = Jwt::encode([
            'sub' => $id,
            'email' => $email
        ]);

        Response::success('Compte administrateur créé.', [
            'token' => $token,
            'admin' => [
                'id' => $id,
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email
            ]
        ], 201);
    }

    public function me(array $payload): never
    {
        Response::success('Session valide.', [
            'id' => $payload['sub'],
            'email' => $payload['email']
        ]);
    }
}
