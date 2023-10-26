<?php

namespace App\Controller;

use App\Entity\Measurement;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Symfony\Component\HttpFoundation\Response;

class MeasurementCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Measurement::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideWhenCreating(),
            DateTimeField::new('timestamp'),
            IntegerField::new('active_power'),
            AssociationField::new('user')->autocomplete(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->addBatchAction(Action::new('sendMeasurements', 'Send Measurements')
                ->linkToCrudAction('sendMeasurements')
                ->addCssClass('btn btn-primary')
                ->setIcon('fa fa-paper-plane'));
    }

    public function sendMeasurements(BatchActionDto $batchActionDto): Response
    {
        //Get entity manager and entity class
        $className = $batchActionDto->getEntityFqcn();
        $entityManager = $this->container->get('doctrine')->getManagerForClass($className);

        //Get measurements from batch action
        $measurementList = [];
        foreach ($batchActionDto->getEntityIds() as $id) {
            $measurement = $entityManager->find($className, $id);
            $measurementList["measurement"][] = $measurement->getActivePower();
        }

        //Setup cURL requesting
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://nilm:5000/prediction");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($measurementList));

        //Execute cURL request
        $response = curl_exec($ch);
        return new Response($response);
    }
}
