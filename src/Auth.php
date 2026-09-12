<?php

declare(strict_types=1);

final class Auth
{
    public function __construct(private PDO $pdo) {}

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if (empty($_SESSION['crm_user_id'])) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT id, name, email, role, is_active FROM crm_users WHERE id = ? LIMIT 1');
        $statement->execute([(int) $_SESSION['crm_user_id']]);
        $user = $statement->fetch();

        if (! $user || ! (bool) $user['is_active']) {
            $this->logout();

            return null;
        }

        return $user;
    }

    public function attempt(string $email, string $password): bool
    {
        $statement = $this->pdo->prepare('SELECT * FROM crm_users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $statement->execute([trim($email)]);
        $user = $statement->fetch();

        if (! $user || ! (bool) $user['is_active'] || ! password_verify($password, $user['password'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['crm_user_id'] = (int) $user['id'];
        unset($_SESSION['login_attempts']);

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['crm_user_id']);
    }

    /** @return array<string, mixed> */
    public function requireUser(): array
    {
        $user = $this->user();
        if (! $user) {
            redirect('');
        }

        return $user;
    }

    /** @return array<string, mixed> */
    public function requireOwner(): array
    {
        $user = $this->requireUser();
        if ($user['role'] !== 'OWNER') {
            http_response_code(403);
            exit('You do not have permission to access this page.');
        }

        return $user;
    }
}
