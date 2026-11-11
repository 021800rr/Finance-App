<?php

declare(strict_types=1);

namespace App\ApiResource\Input;

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\Account;
use App\Entity\AccountOperation;
use App\Entity\Contractor;
use App\Util\BusinessDateRange;
use App\Validator\BusinessDate;
use App\Validator\ContractorGiven;
use App\Validator\DecimalString;
use App\Validator\NoNullCharacter;
use App\Validator\NonZeroAmount;
use App\Validator\RecordExists;
use App\Validator\SelectableAccount;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a client sends to create an operation (POST) or to change one (PATCH). Any field may be missing or null here:
 * the validation then says which one and why, with a code the clients translate. The account is chosen only on
 * create (group operation:create); every other field can be sent both times (operation:write). What the API answers
 * is AccountOperationResource, where every field is always there.
 */
#[ContractorGiven]
final class AccountOperationInput
{
    /**
     * The operation being changed (PATCH), null for a new one (POST): ContractorGiven tells them apart by it.
     */
    #[ApiProperty(description: 'Operation id. Read-only, ignored on write.', writable: false, example: 1)]
    public ?int $id = null;

    #[ApiProperty(
        description: 'Id of the account this operation belongs to. Required on create; a hidden account is rejected; '
        .'it cannot be changed afterwards, so it is not part of the update request.',
        example: 1
    )]
    #[Assert\NotNull(message: 'operation.account_required')]
    #[RecordExists(Account::class, message: 'account.not_found', groups: ['operation:create'])]
    #[SelectableAccount(groups: ['operation:create'])]
    #[Groups(['operation:create'])]
    public ?int $accountId = null;

    #[ApiProperty(
        description: 'Id of an existing contractor. Send it alone, or with the name of the same contractor. '
        .'Null is allowed only on update, together with contractorName, to replace the contractor.',
        example: 10,
    )]
    #[RecordExists(Contractor::class, message: 'contractor.not_found')]
    #[Groups(['operation:write'])]
    public ?int $contractorId = null;

    #[ApiProperty(
        description: 'Contractor name, trimmed; letter case matters. Alone, it finds the contractor or creates a new '
        .'one. With contractorId, it must be the name of that contractor. Null and a blank name are rejected.',
        example: 'Example contractor',
    )]
    #[Assert\Length(max: 255, maxMessage: 'contractor.name_max_length')]
    #[NoNullCharacter]
    #[Groups(['operation:write'])]
    public ?string $contractorName = null;

    #[ApiProperty(description: 'Operation date, format YYYY-MM-DD, from '.BusinessDateRange::MIN_DATE.' to '
        .BusinessDateRange::MAX_DATE.' inclusive. Required.', example: '2026-09-29')]
    #[Assert\NotBlank(message: 'operation.date_not_blank')]
    #[BusinessDate]
    #[Groups(['operation:write'])]
    public ?string $operationDate = null;

    #[ApiProperty(
        description: 'Amount as a decimal string, e.g. "50.00". Positive for income, negative for expense '
        .'(exact sign convention follows the same rule as the Twig form). Cannot be zero. Required.',
        example: '50.00',
    )]
    #[Assert\Sequentially([
        new Assert\NotBlank(message: 'operation.amount_not_blank', normalizer: 'trim'),
        new DecimalString(),
        new Assert\Range(
            notInRangeMessage: 'common.amount_out_of_range',
            invalidMessage: 'common.numeric_invalid',
            min: -999999.99,
            max: 999999.99,
        ),
        new NonZeroAmount(),
    ])]
    #[Groups(['operation:write'])]
    public ?string $amount = null;

    #[ApiProperty(
        description: 'Free-text note. Optional, at most 255 characters after trimming. Surrounding spaces are removed; '
        .'a blank note is stored as null.',
        example: 'Rent for September',
    )]
    #[Assert\Length(max: 255, normalizer: 'trim', maxMessage: 'common.description_max_length')]
    #[NoNullCharacter]
    #[Groups(['operation:write'])]
    public ?string $description = null;

    #[ApiProperty(description: 'Whether the operation is confirmed. Defaults to false.', example: false)]
    #[Groups(['operation:write'])]
    public bool $confirmed = false;

    /**
     * The current values of an operation, for PATCH to lay the request over: what the request leaves out stays.
     */
    public static function fromEntity(AccountOperation $accountOperation): self
    {
        $input = new self();
        $input->id = $accountOperation->getId();
        $input->accountId = $accountOperation->getAccount()->getId();
        $input->contractorId = $accountOperation->getContractor()->getId();
        $input->contractorName = $accountOperation->getContractor()->getName();
        $input->operationDate = $accountOperation->getOperationDate()->format('Y-m-d');
        $input->amount = $accountOperation->getAmountAsMoney()->toDecimalString();
        $input->description = $accountOperation->getDescription();
        $input->confirmed = $accountOperation->isConfirmed();

        return $input;
    }
}
