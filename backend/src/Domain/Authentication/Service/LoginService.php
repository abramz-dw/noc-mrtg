<?php

namespace App\Domain\Authentication\Service;

use App\Infrastructure\Database\Connection;
use App\Domain\Authentication\Entity\User;
use Firebase\JWT\JWT;
use Psr\Log\LoggerInterface;

class LoginService
{
    public function __construct(
        private readonly Connection $db,
        private readonly LoggerInterface $logger,
        private readonly string $jwtSecret,
        private readonly string $jwtAlgorithm,
        private readonly int $jwtExpiration
    ) {}

    /**
     * Login user dan generate JWT token
     * 
     * @param string $username
     * @param string $password
     * @param string $ipAddress
     * @return array{token: string, user: array, expiresIn: int}
     * @throws \Exception
     */
    public function login(string $username, string $password, string $ipAddress): array
    {
        // Get user from database
        $userData = $this->db->queryOne(
            'SELECT id, username, password_hash, full_name, email, role, status, last_login, failed_login_attempts, locked_until FROM users WHERE username = ?',
            [$username]
        );

        if (!$userData) {
            $this->logger->warning('Login failed: user not found', ['username' => $username, 'ip' => $ipAddress]);
            throw new \Exception('Invalid username or password', 401);
        }

        $user = new User(
            id: (int)$userData['id'],
            username: $userData['username'],
            passwordHash: $userData['password_hash'],
            fullName: $userData['full_name'],
            email: $userData['email'],
            role: $userData['role'],
            status: $userData['status'],
            lastLogin: $userData['last_login'],
            failedLoginAttempts: (int)$userData['failed_login_attempts'],
            lockedUntil: $userData['locked_until']
        );

        // Check if user is active
        if (!$user->isActive()) {
            $this->logger->warning('Login failed: user inactive', ['username' => $username]);
            throw new \Exception('User account is inactive', 403);
        }

        // Check if user is locked (5 failed attempts, 1 hour lock)
        if ($user->isLocked()) {
            $this->logger->warning('Login failed: account locked', ['username' => $username]);
            throw new \Exception('Account is locked. Please try again later', 429);
        }

        // Verify password
        if (!password_verify($password, $user->passwordHash)) {
            $this->incrementFailedAttempts($user->id);
            $this->logger->warning('Login failed: invalid password', ['username' => $username, 'ip' => $ipAddress]);
            throw new \Exception('Invalid username or password', 401);
        }

        // Reset failed attempts on successful login
        $this->resetFailedAttempts($user->id);

        // Generate JWT token
        $token = $this->generateToken($user);

        // Update last login
        $this->updateLastLogin($user->id);

        $this->logger->info('Login successful', ['username' => $username, 'ip' => $ipAddress]);

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'fullName' => $user->fullName,
                'email' => $user->email,
                'role' => $user->role
            ],
            'expiresIn' => $this->jwtExpiration
        ];
    }

    /**
     * Generate JWT token
     */
    private function generateToken(User $user): string
    {
        $now = new \DateTime();
        $expiresAt = $now->getTimestamp() + $this->jwtExpiration;
        $jti = bin2hex(random_bytes(16));

        $payload = [
            'iat' => $now->getTimestamp(),
            'exp' => $expiresAt,
            'jti' => $jti,
            'sub' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'fullName' => $user->fullName,
            'role' => $user->role,
        ];

        $token = JWT::encode($payload, $this->jwtSecret, $this->jwtAlgorithm);

        // Store token in database for tracking
        try {
            $this->db->insert('jwt_tokens', [
                'user_id' => $user->id,
                'token_jti' => $jti,
                'expires_at' => date('Y-m-d H:i:s', $expiresAt),
                'created_at' => $now->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to store JWT token', ['error' => $e->getMessage()]);
        }

        return $token;
    }

    /**
     * Verify JWT token
     */
    public function verifyToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, $this->jwtSecret, [$this->jwtAlgorithm]);

            // Check if token is revoked
            $tokenRecord = $this->db->queryOne(
                'SELECT revoked_at FROM jwt_tokens WHERE token_jti = ?',
                [$decoded->jti]
            );

            if ($tokenRecord && $tokenRecord['revoked_at'] !== null) {
                throw new \Exception('Token has been revoked', 401);
            }

            return (array)$decoded;
        } catch (\Exception $e) {
            $this->logger->warning('Invalid token', ['error' => $e->getMessage()]);
            throw new \Exception('Invalid or expired token', 401);
        }
    }

    /**
     * Increment failed login attempts
     */
    private function incrementFailedAttempts(int $userId): void
    {
        $user = $this->db->queryOne(
            'SELECT failed_login_attempts FROM users WHERE id = ?',
            [$userId]
        );

        $attempts = ((int)$user['failed_login_attempts']) + 1;
        $lockedUntil = null;

        // Lock account after 5 failed attempts (1 hour)
        if ($attempts >= 5) {
            $lockedUntil = (new \DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');
        }

        $this->db->update('users', [
            'failed_login_attempts' => $attempts,
            'locked_until' => $lockedUntil
        ], ['id' => $userId]);
    }

    /**
     * Reset failed login attempts
     */
    private function resetFailedAttempts(int $userId): void
    {
        $this->db->update('users', [
            'failed_login_attempts' => 0,
            'locked_until' => null
        ], ['id' => $userId]);
    }

    /**
     * Update last login timestamp
     */
    private function updateLastLogin(int $userId): void
    {
        $this->db->update('users', [
            'last_login' => (new \DateTime())->format('Y-m-d H:i:s')
        ], ['id' => $userId]);
    }
}
