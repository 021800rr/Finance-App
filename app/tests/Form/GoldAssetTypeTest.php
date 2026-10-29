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

final class GoldAssetTypeTest extends KernelTestCase
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
     * @param array<string, string|null> $ounces
     */
    #[DataProvider('blankOunces')]
    public function testBlankOuncesShowsRequiredFieldError(array $ounces): void
    {
        $form = $this->submitOunces($ounces);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('ounces')->getErrors());
        self::assertStringContainsString(
            'Please enter the number of ounces.',
            (string) $form->get('ounces')->getErrors(),
        );
    }

    #[DataProvider('invalidOunces')]
    public function testOuncesRejectsInvalidValue(string $ounces): void
    {
        $form = $this->submitOunces(['ounces' => $ounces]);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('ounces')->getErrors());
    }

    #[DataProvider('validOunces')]
    public function testOuncesAcceptsValidValue(string $ounces): void
    {
        $form = $this->submitOunces(['ounces' => $ounces]);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame($ounces, $form->get('ounces')->getData());
    }

    /**
     * @return array<string, array{array<string, string|null>}>
     */
    public static function blankOunces(): array
    {
        return [
            'empty' => [['ounces' => '']],
            'spaces' => [['ounces' => '   ']],
            'null' => [['ounces' => null]],
            'missing' => [[]],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidOunces(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-0.0001'],
            'above maximum' => ['1000'],
            'not a number' => ['abc'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validOunces(): array
    {
        return [
            'ordinary' => ['1.5'],
            'minimum' => ['0.0001'],
            'maximum' => ['999.9999'],
        ];
    }

    /**
     * @param array<string, string|null> $ounces
     */
    private function submitOunces(array $ounces): FormInterface
    {
        $goldForm = $this->em()->getRepository(GoldForm::class)->findOneBy(['name' => 'sztabka']);
        self::assertNotNull($goldForm);
        /** @var FormFactoryInterface $factory */
        $factory = self::getContainer()->get('form.factory');
        $form = $factory->create(GoldAssetType::class, null, ['csrf_protection' => false]);
        $form->submit([
            'form' => (string) $goldForm->getId(),
            'description' => 'Sztabka testowa',
        ] + $ounces);

        return $form;
    }
}
