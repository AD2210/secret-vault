<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\SecretValueGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class GeneratorController extends AbstractController
{
    #[Route('/generators', name: 'app_generator_index', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, SecretValueGenerator $generator): Response
    {
        $result = null;
        $kind = trim((string) $request->request->get('kind', ''));

        if ($request->isMethod('POST')) {
            try {
                $result = match ($kind) {
                    'password' => [
                        'label' => 'Mot de passe',
                        'values' => [
                            'password' => $generator->generatePassword(
                                (int) $request->request->get('length', 24),
                                $request->request->getBoolean('use_numbers', true),
                                $request->request->getBoolean('use_lowercase', true),
                                $request->request->getBoolean('use_uppercase', true),
                                $request->request->getBoolean('use_specials', true),
                            ),
                        ],
                    ],
                    'hex' => [
                        'label' => 'Clé hexadécimale',
                        'values' => [
                            'hex' => $generator->generateHex((int) $request->request->get('hex_length', 64)),
                        ],
                    ],
                    'ssh_key' => [
                        'label' => 'Clé SSH',
                        'values' => $generator->generateSshKey(
                            (string) $request->request->get('ssh_type', 'ed25519'),
                            (int) $request->request->get('ssh_bits', 4096),
                            '' !== trim((string) $request->request->get('ssh_passphrase', ''))
                                ? (string) $request->request->get('ssh_passphrase')
                                : null,
                        ),
                    ],
                    default => null,
                };
            } catch (\Throwable $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('generator/index.html.twig', [
            'result' => $result,
            'kind' => $kind,
        ]);
    }
}
