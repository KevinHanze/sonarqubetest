<?php
namespace App\Service;

ini_set('memory_limit', '256M');

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use GuzzleHttp\Client;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Measurement;
use App\Entity\User;
use Psr\Log\LoggerInterface;

class ModelManagerClient extends AbstractController
{
	private Client $client;
	private EntityManagerInterface $em;
    private LoggerInterface $logger;

	public function __construct(EntityManagerInterface $em, LoggerInterface $logger)
	{
		$this->client = new Client([
			'base_uri' => 'http://nilm:5000',
			'timeout' => 200000.0,
		]);

		$this->em = $em;
        $this->logger = $logger;
	}

    public function getPrediction(User $user, string $model_id, string $model_type)
    {
        $this->logger->info('Getting prediction started');
        $measurementRepository = $this->em->getRepository(Measurement::class);
        $measurements = $measurementRepository->findMeasurementsLastXAmount($user, 360);

        $measurementList = [];

        foreach ($measurements as $measurement) {
            // Add the measurements to the array
            $dateTime = $measurement->getTimestamp();
            $dateTime->setTimezone(new \DateTimeZone('UTC'));
            $timestamp = $dateTime->format('U');
            $power = $measurement->getActivePower();
            $measurementList[$timestamp] = $power;
        }

        // Sort the array by its keys (timestamps) in ascending order
        ksort($measurementList);

        $this->logger->info('Measurements collected', [
            'measurementList' => $measurementList
        ]);

        $headers = [
            'Content-Type' => 'application/json',
        ];

        // Get current timestamp
        $start_time = time();

        $requestData = [
            'headers' => $headers,
            'json' => [
                'data' => $measurementList,
                'dataset' => $user->getUsername(),
                'output' => 'main',
                'model_id' => $model_id,
                'model_type' => $model_type,
                'start_time' => $start_time,
            ],
        ];

        $this->logger->info('Sending request to Model Manager: ' . json_encode($requestData));

        // Get response from model manager
        $response = $this->client->request('POST', '/predict', $requestData);

        $bodyContent = (string) $response->getBody();
        if ($bodyContent) {
            $this->logger->info('Response received', ['response' => $bodyContent]);
        } else {
            $this->logger->warning('Empty response received');
        }

        // Return response
        return $response;
    }


    public function postMeasurements(string $user): array
	{
		// Get user from database
		$userRepository = $this->em->getRepository(User::class);
		$user = $userRepository->findOneBy(array("username" => strtolower($user)));

		// Get last 5m measurements
		$measurementRepository = $this->em->getRepository(Measurement::class);
		$measurements = $measurementRepository->findMeasurements5m($user);

		$measurementList = [];

		foreach ($measurements as $measurement) {			
			$timestamp = $measurement->getTimestamp()->format('U');
			$power = $measurement->getActivePower();
			$measurementList[$timestamp] = $power;
		}

		// Set headers
		$headers = [
			'Content-Type' => 'application/json',
		];

		// Get response from model manager
		$response = $this->client->request('POST', '/post-dataset', [
			'headers' => $headers,
			'json' => [
				'data' => $measurementList,
				'dataset' => $user->getUsername(),
				'output' => 'main'
			],
		]);

		// Return response
		return json_decode($response->getBody()->getContents(), true);
	}
}
