<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\GoldForm;
use App\Service\GoldAssetFactory;
use App\Tests\DatabaseTestTrait;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class GoldAssetFactoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private GoldAssetFactory $factory;
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->initEntityManager($kernel);

        $container = self::getContainer();

        /** @var GoldAssetFactory $factory */
        $factory = $container->get(GoldAssetFactory::class);
        $this->factory = $factory;

        /** @var FormFactoryInterface $formFactory */
        $formFactory = $container->get('form.factory');
        $this->formFactory = $formFactory;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    public function testCreateFromFormBuildsAssetWithCorrectOunces(): void
    {
        $goldForm = $this->createGoldForm();
        $form = $this->buildForm($goldForm, 1.5, 'Sztabka 1oz');

        $asset = $this->factory->createFromForm($form);

        self::assertSame($goldForm->getId(), $asset->getForm()->getId());
        self::assertSame(15000, $asset->getOunces());
        self::assertSame('Sztabka 1oz', $asset->getDescription());
    }

    public function testUpdateFromFormUpdatesExistingAsset(): void
    {
        $goldForm = $this->createGoldForm();
        $originalForm = $this->buildForm($goldForm, 1.0, 'Original');
        $asset = $this->factory->createFromForm($originalForm);

        $newGoldForm = $this->createGoldForm();
        $updateForm = $this->buildForm($newGoldForm, 2.25, 'Updated');
        $this->factory->updateFromForm($asset, $updateForm);

        self::assertSame($newGoldForm->getId(), $asset->getForm()->getId());
        self::assertSame(22500, $asset->getOunces());
        self::assertSame('Updated', $asset->getDescription());
    }

    public function testCreateFromFormThrowsWhenGoldFormIsMissing(): void
    {
        $form = $this->buildFormWithoutGoldForm();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gold form is required.');

        $this->factory->createFromForm($form);
    }

    public function testCreateFromFormThrowsWhenDescriptionIsBlank(): void
    {
        $goldForm = $this->createGoldForm();
        $form = $this->buildForm($goldForm, 1.5, '   ');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Description is required.');

        $this->factory->createFromForm($form);
    }

    public function testCreateFromFormThrowsWhenDescriptionIsMissing(): void
    {
        $goldForm = $this->createGoldForm();
        $form = $this->buildForm($goldForm, 1.5, null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Description is required.');

        $this->factory->createFromForm($form);
    }

    private function buildForm(GoldForm $goldForm, float $ounces, ?string $description): FormInterface
    {
        $form = $this->formFactory->createNamed('', FormType::class, null, ['csrf_protection' => false]);
        $form->add('form', HiddenType::class, ['mapped' => false]);
        $form->add('ounces', NumberType::class, ['mapped' => false, 'input' => 'string']);
        $form->add('description', HiddenType::class, ['mapped' => false, 'required' => false]);

        $form->get('form')->setData($goldForm);
        $form->get('ounces')->setData((string) $ounces);
        $form->get('description')->setData($description);

        return $form;
    }

    private function buildFormWithoutGoldForm(): FormInterface
    {
        $form = $this->formFactory->createNamed('', FormType::class, null, ['csrf_protection' => false]);
        $form->add('form', HiddenType::class, ['mapped' => false]);
        $form->add('ounces', NumberType::class, ['mapped' => false, 'input' => 'string']);
        $form->add('description', HiddenType::class, ['mapped' => false, 'required' => false]);

        $form->get('form')->setData(null);
        $form->get('ounces')->setData('1.5');
        $form->get('description')->setData('Test');

        return $form;
    }

    private function createGoldForm(): GoldForm
    {
        $goldForm = new GoldForm('Test Form ' . uniqid());
        $this->em()->persist($goldForm);
        $this->em()->flush();

        return $goldForm;
    }
}
