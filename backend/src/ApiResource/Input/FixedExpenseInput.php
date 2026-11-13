<?php

declare(strict_types=1);

namespace App\ApiResource\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\Contractor;
use App\Entity\FixedExpense;
use App\Validator\ContractorGiven;
use App\Validator\DecimalString;
use App\Validator\NoNullCharacter;
use App\Validator\RecordExists;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a client sends to create a fixed expense (POST) or to change one (PATCH). Any field may be missing or null
 * here: the validation then says which one and why, with a code the clients translate. What the API answers is
 * FixedExpenseResource, where every field is always there.
 */
#[ContractorGiven]
final class FixedExpenseInput
{
    /**
     * The fixed expense being changed (PATCH), null for a new one (POST): ContractorGiven tells them apart by it.
     */
    #[ApiProperty(description: 'Fixed expense id. Read-only, ignored on write.', writable: false, example: 1)]
    public ?int $id = null;

    #[ApiProperty(
        description: 'Id of an existing contractor. Send it alone, or with the name of the same contractor. '
        .'Null is allowed only on update, together with contractorName, to replace the contractor.',
        example: 10,
    )]
    #[RecordExists(Contractor::class, message: 'contractor.not_found')]
    public ?int $contractorId = null;

    #[ApiProperty(
        description: 'Contractor name, trimmed; letter case matters. Alone, it finds the contractor or creates a new '
        .'one. With contractorId, it must be the name of that contractor. Null and a blank name are rejected.',
        example: 'Example contractor',
    )]
    #[Assert\Length(max: 255, maxMessage: 'contractor.name_max_length')]
    #[NoNullCharacter]
    public ?string $contractorName = null;

    #[ApiProperty(description: 'Day of the month the expense is due, 1-28. Required.', example: 10)]
    #[Assert\NotNull(message: 'fixed_expense.day_required')]
    #[Assert\Range(notInRangeMessage: 'fixed_expense.day_out_of_range', min: 1, max: 28)]
    public ?int $day = null;

    #[ApiProperty(
        description: 'Amount as a decimal string. Must be negative, e.g. "-200.00". Always in PLN. Required.',
        example: '-200.00',
    )]
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'fixed_expense.amount_not_blank', normalizer: 'trim'),
        new DecimalString(),
        new Assert\Range(
            notInRangeMessage: 'fixed_expense.amount_out_of_range',
            invalidMessage: 'common.numeric_invalid',
            min: -999999.99,
            max: -0.01,
        ),
    ])]
    public ?string $amount = null;

    #[ApiProperty(
        description: 'Free-text note. Optional, at most 255 characters after trimming. Surrounding spaces are removed; '
        .'a blank note is stored as null.',
        example: 'Rent',
    )]
    #[Assert\Length(max: 255, normalizer: 'trim', maxMessage: 'common.description_max_length')]
    #[NoNullCharacter]
    public ?string $description = null;

    /**
     * The current values of a fixed expense, for PATCH to lay the request over: what the request leaves out stays.
     */
    public static function fromEntity(FixedExpense $fixedExpense): self
    {
        $input = new self();
        $input->id = $fixedExpense->getId();
        $input->contractorId = $fixedExpense->getContractor()->getId();
        $input->contractorName = $fixedExpense->getContractor()->getName();
        $input->day = $fixedExpense->getDay();
        $input->amount = $fixedExpense->getAmountAsMoney()->toDecimalString();
        $input->description = $fixedExpense->getDescription();

        return $input;
    }
}
