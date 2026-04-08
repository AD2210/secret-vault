<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class SecretValueGenerator
{
    public function generatePassword(
        int $length,
        bool $useNumbers,
        bool $useLowercase,
        bool $useUppercase,
        bool $useSpecials,
    ): string {
        $groups = [];
        if ($useLowercase) {
            $groups[] = 'abcdefghijklmnopqrstuvwxyz';
        }
        if ($useUppercase) {
            $groups[] = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        if ($useNumbers) {
            $groups[] = '0123456789';
        }
        if ($useSpecials) {
            $groups[] = '!@#$%^&*()-_=+[]{}:,.?';
        }

        if ([] === $groups) {
            throw new \InvalidArgumentException('At least one character group must be selected.');
        }

        $length = max($length, count($groups));
        $characters = [];
        foreach ($groups as $group) {
            $characters[] = $group[random_int(0, strlen($group) - 1)];
        }

        $pool = implode('', $groups);
        while (count($characters) < $length) {
            $characters[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        shuffle($characters);

        return implode('', $characters);
    }

    public function generateHex(int $length): string
    {
        $length = max(2, $length);
        $bytes = (int) ceil($length / 2);

        return substr(bin2hex(random_bytes($bytes)), 0, $length);
    }

    /**
     * @return array{ssh_type: string, private_key: string, public_key: string, passphrase: ?string}
     */
    public function generateSshKey(string $type, int $bits, ?string $passphrase = null): array
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['ed25519', 'rsa'], true)) {
            throw new \InvalidArgumentException('Unsupported SSH key type.');
        }

        $tempDir = sys_get_temp_dir().'/vault-ssh-'.bin2hex(random_bytes(6));
        mkdir($tempDir, 0700, true);
        $keyPath = $tempDir.'/id_'.$type;

        $command = ['ssh-keygen', '-q', '-t', $type, '-f', $keyPath, '-N', $passphrase ?? '', '-C', 'client-secret-vault'];
        if ('rsa' === $type) {
            $command[] = '-b';
            $command[] = (string) max(2048, $bits);
        }

        $process = new Process($command);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $privateKey = (string) file_get_contents($keyPath);
        $publicKey = (string) file_get_contents($keyPath.'.pub');
        @unlink($keyPath);
        @unlink($keyPath.'.pub');
        @rmdir($tempDir);

        return [
            'ssh_type' => $type,
            'private_key' => trim($privateKey),
            'public_key' => trim($publicKey),
            'passphrase' => $passphrase,
        ];
    }
}
