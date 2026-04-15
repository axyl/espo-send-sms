<?php

namespace Espo\Custom\Tools\Activities\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\SearchParamsFetcher;
use Espo\Custom\Tools\Activities\MergedService;
use Espo\Custom\Tools\Activities\SmsRowNormalizer;
use Espo\Entities\Sms;
use Espo\Modules\Crm\Tools\Activities\FetchParams as ActivitiesFetchParams;
use Espo\Modules\Crm\Tools\Activities\Service as CoreService;

class Get implements Action
{
    public function __construct(
        private SearchParamsFetcher $searchParamsFetcher,
        private MergedService $mergedService,
        private CoreService $coreService,
        private Acl $acl,
        private SmsRowNormalizer $smsRowNormalizer,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->check('Activities')) {
            throw new Forbidden();
        }

        $parentType = $request->getRouteParam('parentType');
        $id = $request->getRouteParam('id');
        $type = $request->getRouteParam('type');

        if (!$parentType || !$id || !in_array($type, ['activities', 'history'])) {
            throw new BadRequest();
        }

        $searchParams = $this->searchParamsFetcher->fetch($request);
        $targetEntityType = $request->getQueryParam('entityType') ?: null;

        $fetchParams = new ActivitiesFetchParams(
            $searchParams->getMaxSize(),
            $searchParams->getOffset(),
            $targetEntityType
        );

        if ($targetEntityType === null || $targetEntityType === Sms::ENTITY_TYPE) {
            // Keep Sms and mixed requests on the custom route/service path.
            // Replacing this with a naive call to the core Activities endpoint
            // previously regressed into HTTP 500s caused by UNION column-count
            // mismatches in the generated SQL.
            $result = $type === 'history' ?
                $this->mergedService->getHistory($parentType, $id, $fetchParams) :
                $this->mergedService->getActivities($parentType, $id, $fetchParams);

            return ResponseComposer::json((object) [
                'total' => $result->getTotal(),
                'list' => $this->smsRowNormalizer->normalizeList($result->getValueMapList()),
            ]);
        }

        $result = $type === 'history' ?
            $this->coreService->getHistory($parentType, $id, $fetchParams) :
            $this->coreService->getActivities($parentType, $id, $fetchParams);

        return ResponseComposer::json((object) [
            'total' => $result->getTotal(),
            'list' => $this->smsRowNormalizer->normalizeList($result->getValueMapList()),
        ]);
    }
}
