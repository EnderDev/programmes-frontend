<?php
declare(strict_types = 1);
namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use DateTimeImmutable;

class StatusController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // If the load balancer is pinging us then give them a plain OK
        if ($request->headers->get('User-Agent') == 'ELB-HealthChecker/1.0') {
            return new Response('OK', Response::HTTP_OK, ['content-type' => 'text/plain']);
        }

        // Other people get a better info screen
        $dbalConnection = $this->get('doctrine.dbal.default_connection');

        return $this->render('@App/status/status.html.twig', [
            'now' => new DateTimeImmutable(),
            'dbConnectivity' => $dbalConnection->isConnected() || $dbalConnection->connect(),
        ]);
    }
}