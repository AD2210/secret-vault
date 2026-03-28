<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\TotpSetupType;
use App\Security\TotpQrCodeFactory;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/t/{tenantSlug}/security/2fa/setup')]
#[IsGranted('ROLE_USER')]
final class TwoFactorSetupController extends AbstractController
{
    public function __construct(
        private readonly TotpAuthenticatorInterface $totpAuthenticator,
        private readonly TotpQrCodeFactory $qrCodeFactory,
    ) {
    }

    #[Route('', name: 'app_2fa_setup', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getCurrentUser();
        if ($user->isTotpAuthenticationEnabled()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if (null === $user->getTotpSecret()) {
            $user->prepareTotp($this->totpAuthenticator->generateSecret());
            $em->flush();
        }

        $form = $this->createForm(TotpSetupType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $code = (string) $form->get('code')->getData();
            if ($this->totpAuthenticator->checkCode($user, $code)) {
                $user->enableTotp();
                $em->flush();

                $this->addFlash('success', 'Le double facteur est activé. Votre coffre-fort est maintenant verrouillé en MFA.');

                return $this->redirectToRoute('app_dashboard');
            }

            $this->addFlash('error', 'Le code fourni est invalide.');
        }

        $qrPayload = $this->totpAuthenticator->getQRContent($user);

        return $this->render('security/totp_setup.html.twig', [
            'form' => $form,
            'qrCodeDataUri' => $this->qrCodeFactory->asDataUri($qrPayload),
            'manualSecret' => $user->getTotpSecret(),
            'qrPayload' => $qrPayload,
        ]);
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
