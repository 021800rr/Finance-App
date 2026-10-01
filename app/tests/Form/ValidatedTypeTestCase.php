<?php

declare(strict_types=1);

namespace App\Tests\Form;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

#[AllowMockObjectsWithoutExpectations]
abstract class ValidatedTypeTestCase extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }
}
