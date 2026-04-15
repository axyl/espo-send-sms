<?php

namespace Espo\Custom\Tools\Activities\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Record\SearchParamsFetcher;
use Espo\Custom\Tools\Activities\MergedService;
use Espo\Custom\Tools\Activities\SmsRowNormalizer;
use Espo\Entities\Sms;
use Espo\Modules\Crm\Tools\Activities\FetchParams as ActivitiesFetchParams;
use Espo\Modules\Crm\Tools\Activities\Service as CoreService;

class GetListTyped implements Action
{
    public function __construct(
        private SearchParamsFetcher $searchParamsFetcher,
        private MergedService $mergedService,
        private CoreService $coreService,
        private SmsRowNormalizer $smsRowNormalizer,
    ) {}

    public function process(Request $request): Response
    {
        $parentType = $request->getRouteParam('parentType');
        $id = $request->getRouteParam('id');
        $type = $request->getRouteParam('type');
        $targetType = $request->getRouteParam('targetType');

        if (!$parentType || !$id || !$type || !$targetType) {
            throw new BadRequest();
        }

        if ($type !== 'activities' && $type !== 'history') {
            throw new BadRequest("Bad type.");
        }

        if ($targetType === Sms::ENTITY_TYPE) {
            // Typed Sms requests stay on the custom merged path for the same
            // reason as the generic route: the validated behavior is to keep
            // Sms out of the core mixed UNION query generation.
            $searchParams = $this->searchParamsFetcher->fetch($request);
            $fetchParams = new ActivitiesFetchParams(
                $searchParams->getMaxSize(),
                $searchParams->getOffset(),
                Sms::ENTITY_TYPE
            );

            $result = $type === 'history' ?
                $this->mergedService->getHistory($parentType, $id, $fetchParams) :
                $this->mergedService->getActivities($parentType, $id, $fetchParams);

            return ResponseComposer::json((object) [
                'total' => $result->getTotal(),
                'list' => $this->smsRowNormalizer->normalizeList($result->getValueMapList()),
            ]);
        }

        $searchParams = $this->searchParamsFetcher->fetch($request);

        $result = $this->coreService->findActivitiesEntityType(
            $parentType,
            $id,
            $targetType,
            $type === 'history',
            $searchParams
        );

        return ResponseComposer::json((object) [
            'total' => $result->getTotal(),
            'list' => $this->smsRowNormalizer->normalizeList($result->getValueMapList()),
        ]);
    }
}
