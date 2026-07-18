<?php

declare(strict_types=1);

namespace App\Controller\Garmin;

use App\Domain\Garmin\Garmin;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class GarminOAuthRequestHandler
{
    public function __construct(
        private Garmin $garmin,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/garmin-oauth', name: 'garmin_oauth', methods: ['GET'], priority: 2)]
    public function handle(Request $request): Response
    {
        $redirectUri = $request->getSchemeAndHttpHost().'/garmin-oauth';

        if ($error = $request->query->get('error')) {
            return new Response($this->twig->render('html/garmin-oauth/error-page.html.twig', [
                'error' => (string) $error,
            ]));
        }

        if ($code = $request->query->get('code')) {
            try {
                $this->garmin->handleAuthorizationCallback(
                    code: (string) $code,
                    state: (string) $request->query->get('state', ''),
                    redirectUri: $redirectUri,
                );

                return new Response($this->twig->render('html/garmin-oauth/connected.html.twig'));
            } catch (\Throwable $e) {
                return new Response($this->twig->render('html/garmin-oauth/error-page.html.twig', [
                    'error' => $e->getMessage(),
                ]), Response::HTTP_OK);
            }
        }

        if ($request->query->getBoolean('start')) {
            return new RedirectResponse($this->garmin->createAuthorizationUrl($redirectUri));
        }

        return new Response($this->twig->render('html/garmin-oauth/start-authorization.html.twig', [
            'configured' => $this->garmin->isConfigured(),
            'authorized' => $this->garmin->isAuthorized(),
        ]));
    }
}
