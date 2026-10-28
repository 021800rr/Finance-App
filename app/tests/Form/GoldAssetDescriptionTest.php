<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\GoldForm;
use App\Form\GoldAssetType;
use App\Tests\DatabaseTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

final class GoldAssetDescriptionTest extends KernelTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        $this->initEntityManager(self::bootKernel());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->closeEntityManager();
    }

    /**
     * @param array<string, string|null> $description
     */
    #[DataProvider('blankDescriptions')]
    public function testDescriptionRejectsBlankInput(array $description): void
    {
        $form = $this->submitDescription($description);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('description')->getErrors());
        self::assertStringContainsString(
            'Please enter a description.',
            (string) $form->get('description')->getErrors(),
        );
    }

    public function testDescriptionAcceptsText(): void
    {
        $form = $this->submitDescription(['description' => 'Sztabka 1oz']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('Sztabka 1oz', $form->get('description')->getData());
    }

    /**
     * @return array<string, array{array<string, string|null>}>
     */
    public static function blankDescriptions(): array
    {
        return [
            'empty' => [['description' => '']],
            'spaces' => [['description' => '   ']],
            'null' => [['description' => null]],
            'missing' => [[]],
        ];
    }

    /**
     * @param array<string, string|null> $description
     */
    private function submitDescription(array $description): FormInterface
    {
        $goldForm = $this->em()->getRepository(GoldForm::class)->findOneBy(['name' => 'sztabka']);
        self::assertNotNull($goldForm);
        /** @var FormFactoryInterface $factory */
        $factory = self::getContainer()->get('form.factory');
        $form = $factory->create(GoldAssetType::class, null, ['csrf_protection' => false]);
        $form->submit(['form' => (string) $goldForm->getId(), 'ounces' => '1.5'] + $description);

        return $form;
    }
}
