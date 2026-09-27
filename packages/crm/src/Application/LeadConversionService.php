<?php

declare(strict_types=1);

namespace Kontor\CRM\Application;

use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\CRM\Infrastructure\Persistence\LeadRepository;
use Kontor\CRM\Infrastructure\Persistence\PipelineRepository;
use Kontor\CRM\Infrastructure\Persistence\StageRepository;
use RuntimeException;

/**
 * Substage 3.3 "conversion" — kontor.md diagram 17.1: "Convert to
 * contact/company -> Create deal". A lead's schema (kontor.md#13.1) has
 * no name/email columns of its own, only contact_uid/company_uid — so
 * "qualified" means the lead is already linked to one, and conversion is
 * "create a deal from this lead", not "create a contact from this lead".
 */
final class LeadConversionService
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly PipelineRepository $pipelines,
        private readonly StageRepository $stages,
        private readonly DealRepository $deals,
    ) {
    }

    public function convert(string $leadUid): Deal
    {
        $lead = $this->leads->require($leadUid);

        if ($lead->isConverted()) {
            throw new RuntimeException("Lead \"{$leadUid}\" was already converted to deal \"{$lead->convertedDealUid}\".");
        }

        if (!$lead->isQualifiedForConversion()) {
            throw new RuntimeException(
                "Lead \"{$leadUid}\" cannot be converted: it must be linked to a contact or company first."
            );
        }

        $pipeline = $this->pipelines->defaultForEntityType($lead->organizationId, 'deal')
            ?? throw new RuntimeException("No default deal pipeline is configured for organization \"{$lead->organizationId}\".");

        $stage = $this->stages->firstOpenStage($pipeline->uid->toString())
            ?? throw new RuntimeException("Pipeline \"{$pipeline->uid}\" has no open stage to place the new deal in.");

        $deal = Deal::create(
            organizationId: $lead->organizationId,
            pipelineUid: $pipeline->uid->toString(),
            stageUid: $stage->uid->toString(),
            title: $lead->title,
            contactUid: $lead->contactUid,
            companyUid: $lead->companyUid,
            assignedUserId: $lead->assignedUserId,
            value: $lead->estimatedValue,
            probability: $stage->probability,
            source: $lead->source,
            description: $lead->description,
        );

        $this->deals->save($deal);

        $lead->status = 'converted';
        $lead->convertedDealUid = $deal->uid->toString();
        $this->leads->save($lead);

        return $deal;
    }
}
