<?php

namespace App\Controller;

use App\Entity\Measurement;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\ModelManagerClient;

class ApiController extends AbstractController
{
    private ModelManagerClient $modelManagerClient;

    public function __construct(ModelManagerClient $modelManagerClient)
    {
	$this->modelManagerClient = $modelManagerClient;
    }

    #[Route('/api', name: 'app_api', methods: ['GET'])]
    public function index(): JsonResponse
    {
	// Return a JSON response
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/ApiController.php',
        ]);
    }

    /**
     * @throws \Exception
     */
    #[Route('/measurement-test', name: '5g_test', methods: ['POST'])]
    public function measurementTest(Request $request): JsonResponse
    {

        $filename = 'measurements-5g-test';
        $body = json_decode($request->getContent(), true);

        if(!file_exists($filename)) {
            touch($filename);
        }

        $time = time()+7200;
        $timestamp = gmdate('r', $time);
        $measurement = $body["measurement"];

        $file = fopen($filename,'a');
        fwrite($file, "measurement: ".$measurement . ", saved at: " . $timestamp. "\n");

        return $this->json([
            "message"=> 'measurement: '.$measurement . ", was saved at: " . $timestamp,
        ]);

    }


    #[Route('/measurement-test123', name: '5g_test', methods: ['POST'])]
    public function measurementTest123(Request $request): string
    {
        return "kaas";
    }

    #[Route('/feedback', name: 'app_recieve_feedback', methods: ['POST'])]
    public function recieveFeedback(Request $request): Response
    {
        // Get the request body
        $body = json_decode($request->getContent(), true);

        // Get feedback from request
        $device = $body["device"];
        $time =   $body["time"];
        $feedback = $body["feedback"];

        return new Response($device . " " . $time . " " . $feedback);

    }


    #[Route('/post-measurements', name: 'app_post_measurements', methods: ['POST'])]
    public function postMeasurements(ValidatorInterface $validator, EntityManagerInterface $em, Request $request): Response
    {
	// Get the request body
	$body = json_decode($request->getContent(), true);

	// Get the user from the database
        $userRepository = $em->getRepository(User::class);
        $user = $userRepository->findOneBy(array("username" => strtolower($body["owner"])));

	// For each measurement in the request body, create a new Measurement object and save it to the database
        foreach ($body["measurements"] as $data) {
            try{
                $measurement = new Measurement();
                $measurement
                    ->setUser($user)
                    ->setTimestamp(new \DateTime())
                    ->setActivePower($data["active_power"]);

		// Validate the Measurement object
                $errors = $validator->validate($measurement);

		// If there are any errors, return them
                if (count($errors) > 0) {
                    $errorsString = (string) $errors;
                    return new Response($errorsString);
                }

		// Save the Measurement object to the database
                $em->persist($measurement);
		$em->flush();

	    } catch (\Throwable $th) {
		    // If there is an error, return it
                return new Response($th->getMessage());
            }
	}
	// Return a success message
        return new Response("Measurements saved");
    }

    #[Route('/api/measurements', name: 'app_get_measurements_24h', methods: ['GET'])]
    public function getMeasurements24h(Request $request, EntityManagerInterface $em): Response
    {
	// Get the user from the database
	$userRepository = $em->getRepository(User::class);
	$user = $userRepository->findOneBy(array("username" => strtolower($request->query->get("owner"))));

	// Get the measurements from the database 
	$measurementRepository = $em->getRepository(Measurement::class);
	$measurements = $measurementRepository->findMeasurements24h($user);

	// Create an empty array
	$measurementList = [];

	foreach ($measurements as $measurement) {
		// Add the measurements to the array
		$id = $measurement->getId();
		$measurementList[$id]["timestamp"] = $measurement->getTimestamp()->format('Y-m-d H:i:s');
		$measurementList[$id]["active_power"] = $measurement->getActivePower();
	}

	// Return the measurements as a JSON response
	return new JsonResponse($measurementList);
    }

    #[Route('/api/predict', name: 'app_predict', methods: ['GET'])]
    public function predict(EntityManagerInterface $em): Response
    {
	return new JsonResponse($this->modelManagerClient->getPrediction('kevin'));
    }

    #[Route('/api/predictions', name: 'app_get_predictions_5m', methods: ['GET'])]
    public function getPredictions5m(Request $request, EntityManagerInterface $em): Response
    {
        // Get the user from the database
        $userRepository = $em->getRepository(User::class);
        $user = $userRepository->findOneBy(array("username" => strtolower($request->query->get("dataset"))));
        $model_type = $request->query->get("model_type");
        $model_id = $request->query->get("model_id");

        // If user does not exist, return an error response
        if (!$user) {
            return new JsonResponse(["error" => "User not found"], 400);
        }

        // Get prediction
        $response = $this->modelManagerClient->getPrediction($user, $model_id, $model_type);

        // Decode the response body
        $json = json_decode($response->getBody(), true);

        // Return the entire response as a JSON response
        return new JsonResponse($json);
    }

}
