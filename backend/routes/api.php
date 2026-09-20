<?php

use App\Http\Controllers\Api\V1\ActivityFeedController;
use App\Http\Controllers\Api\V1\AdminArtistIndexController;
use App\Http\Controllers\Api\V1\AdminArtworkIndexController;
use App\Http\Controllers\Api\V1\AdminHolderIndexController;
use App\Http\Controllers\Api\V1\ArchiveItemDestroyController;
use App\Http\Controllers\Api\V1\ArchiveItemIndexController;
use App\Http\Controllers\Api\V1\ArchiveItemLinkDestroyController;
use App\Http\Controllers\Api\V1\ArchiveItemLinkStoreController;
use App\Http\Controllers\Api\V1\ArchiveItemPublishController;
use App\Http\Controllers\Api\V1\ArchiveItemRestoreController;
use App\Http\Controllers\Api\V1\ArchiveItemShowController;
use App\Http\Controllers\Api\V1\ArchiveItemStoreController;
use App\Http\Controllers\Api\V1\ArchiveItemUpdateController;
use App\Http\Controllers\Api\V1\ArtistArchiveItemsController;
use App\Http\Controllers\Api\V1\ArtistArtworksController;
use App\Http\Controllers\Api\V1\ArtistCurationShowController;
use App\Http\Controllers\Api\V1\ArtistCurationUpdateController;
use App\Http\Controllers\Api\V1\ArtistDestroyController;
use App\Http\Controllers\Api\V1\ArtistEntriesSyncController;
use App\Http\Controllers\Api\V1\ArtistIndexController;
use App\Http\Controllers\Api\V1\ArtistMergeController;
use App\Http\Controllers\Api\V1\ArtistPortraitController;
use App\Http\Controllers\Api\V1\ArtistRestoreController;
use App\Http\Controllers\Api\V1\ArtistShowController;
use App\Http\Controllers\Api\V1\ArtistSocialLinksSyncController;
use App\Http\Controllers\Api\V1\ArtistStoreController;
use App\Http\Controllers\Api\V1\ArtistUnverifyController;
use App\Http\Controllers\Api\V1\ArtistUpdateController;
use App\Http\Controllers\Api\V1\ArtistVariantDestroyController;
use App\Http\Controllers\Api\V1\ArtistVariantStoreController;
use App\Http\Controllers\Api\V1\ArtistVariantUpdateController;
use App\Http\Controllers\Api\V1\ArtistVerifyController;
use App\Http\Controllers\Api\V1\ArtworkApproveController;
use App\Http\Controllers\Api\V1\ArtworkArchiveItemsController;
use App\Http\Controllers\Api\V1\ArtworkCurationShowController;
use App\Http\Controllers\Api\V1\ArtworkDestroyController;
use App\Http\Controllers\Api\V1\ArtworkImageController;
use App\Http\Controllers\Api\V1\ArtworkIndexController;
use App\Http\Controllers\Api\V1\ArtworkMergeController;
use App\Http\Controllers\Api\V1\ArtworkPipelineShowController;
use App\Http\Controllers\Api\V1\ArtworkPipelineUpdateController;
use App\Http\Controllers\Api\V1\ArtworkRestoreController;
use App\Http\Controllers\Api\V1\ArtworkShowController;
use App\Http\Controllers\Api\V1\ArtworkStoreController;
use App\Http\Controllers\Api\V1\ArtworkUpdateController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\UserController;
use App\Http\Controllers\Api\V1\CandidateArtworkDismissController;
use App\Http\Controllers\Api\V1\CandidateArtworkIndexController;
use App\Http\Controllers\Api\V1\CandidateArtworkPromoteController;
use App\Http\Controllers\Api\V1\DashboardCompletenessController;
use App\Http\Controllers\Api\V1\DashboardExportController;
use App\Http\Controllers\Api\V1\DashboardRecordsController;
use App\Http\Controllers\Api\V1\FieldCitationDestroyController;
use App\Http\Controllers\Api\V1\FieldCitationStoreController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HolderDestroyController;
use App\Http\Controllers\Api\V1\HolderRestoreController;
use App\Http\Controllers\Api\V1\HolderShowController;
use App\Http\Controllers\Api\V1\HolderStoreController;
use App\Http\Controllers\Api\V1\HolderUpdateController;
use App\Http\Controllers\Api\V1\ImportBatchCancelController;
use App\Http\Controllers\Api\V1\ImportBatchCommitController;
use App\Http\Controllers\Api\V1\ImportBatchIndexController;
use App\Http\Controllers\Api\V1\ImportBatchRevalidateController;
use App\Http\Controllers\Api\V1\ImportBatchRowIndexController;
use App\Http\Controllers\Api\V1\ImportBatchRowUpdateController;
use App\Http\Controllers\Api\V1\ImportBatchShowController;
use App\Http\Controllers\Api\V1\ImportBatchStoreController;
use App\Http\Controllers\Api\V1\ImportMappingProfileIndexController;
use App\Http\Controllers\Api\V1\ImportMappingProfileStoreController;
use App\Http\Controllers\Api\V1\PipelineNoteSuggestionAcceptController;
use App\Http\Controllers\Api\V1\RecordCompletenessController;
use App\Http\Controllers\Api\V1\ReviewQueueAcknowledgeController;
use App\Http\Controllers\Api\V1\ReviewQueueIndexController;
use App\Http\Controllers\Api\V1\SourceConflictResolveController;
use App\Http\Controllers\Api\V1\SubjectActivityController;
use App\Http\Controllers\Api\V1\ThemeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);

    Route::post('auth/login', LoginController::class)->middleware('throttle:login');

    Route::middleware('throttle:api')->group(function () {
        Route::get('artists', ArtistIndexController::class);
        Route::get('artists/{artist}/portrait', [ArtistPortraitController::class, 'show'])->whereNumber('artist');
        Route::get('artists/{artist}/artworks', ArtistArtworksController::class)->whereNumber('artist');
        Route::get('artists/{slug}', ArtistShowController::class)->where('slug', '[a-z0-9-]+');

        Route::get('artworks', ArtworkIndexController::class);
        Route::get('artworks/{artwork}', ArtworkShowController::class)->whereNumber('artwork');
        Route::get('artworks/{artwork}/archive-items', ArtworkArchiveItemsController::class)->whereNumber('artwork');

        Route::get('artworks/{artwork}/images/{image}/file', [ArtworkImageController::class, 'show'])->whereNumber(['artwork', 'image']);
        Route::get('holders/{holder}', HolderShowController::class)->whereNumber('holder');

        Route::get('archive-items', ArchiveItemIndexController::class);
        Route::get('archive-items/{archiveItem}', ArchiveItemShowController::class)->whereNumber('archiveItem');
        Route::get('artists/{artist}/archive-items', ArtistArchiveItemsController::class)->whereNumber('artist');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/user', UserController::class);
        Route::post('auth/logout', LogoutController::class);

        Route::post('artists', ArtistStoreController::class)->middleware('can:create,App\Models\Artist');
        Route::patch('artists/{artist}', ArtistUpdateController::class)->whereNumber('artist')->middleware('can:update,artist');
        Route::delete('artists/{artist}', ArtistDestroyController::class)->whereNumber('artist')->middleware('can:delete,artist');
        Route::post('artists/{id}/restore', ArtistRestoreController::class)->whereNumber('id')->middleware('can:restore,App\Models\Artist');

        Route::post('artists/{artist}/variants', ArtistVariantStoreController::class)->whereNumber('artist')->middleware('can:update,artist');
        Route::patch('artists/{artist}/variants/{variant}', ArtistVariantUpdateController::class)
            ->whereNumber('artist')->whereNumber('variant')->middleware('can:update,artist');
        Route::delete('artists/{artist}/variants/{variant}', ArtistVariantDestroyController::class)
            ->whereNumber('artist')->whereNumber('variant')->middleware('can:update,artist');

        Route::post('artists/{artist}/verify', ArtistVerifyController::class)->whereNumber('artist')->middleware('can:verify,artist');
        Route::delete('artists/{artist}/verify', ArtistUnverifyController::class)->whereNumber('artist')->middleware('can:verify,artist');

        Route::post('artworks', ArtworkStoreController::class)->middleware('can:create,App\Models\Artwork');
        Route::patch('artworks/{artwork}', ArtworkUpdateController::class)->whereNumber('artwork')->middleware('can:update,artwork');
        Route::delete('artworks/{artwork}', ArtworkDestroyController::class)->whereNumber('artwork')->middleware('can:delete,artwork');
        Route::post('artworks/{id}/restore', ArtworkRestoreController::class)->whereNumber('id')->middleware('can:restore,App\Models\Artwork');

        Route::post('holders', HolderStoreController::class)->middleware('can:create,App\Models\Holder');
        Route::patch('holders/{holder}', HolderUpdateController::class)->whereNumber('holder')->middleware('can:update,holder');
        Route::delete('holders/{holder}', HolderDestroyController::class)->whereNumber('holder')->middleware('can:delete,holder');
        Route::post('holders/{id}/restore', HolderRestoreController::class)->whereNumber('id')->middleware('can:restore,App\Models\Holder');

        Route::post('archive-items', ArchiveItemStoreController::class)->middleware('can:create,App\Models\ArchiveItem');
        Route::patch('archive-items/{archiveItem}', ArchiveItemUpdateController::class)->whereNumber('archiveItem')->middleware('can:update,archiveItem');
        Route::delete('archive-items/{archiveItem}', ArchiveItemDestroyController::class)->whereNumber('archiveItem')->middleware('can:delete,archiveItem');
        Route::post('archive-items/{id}/restore', ArchiveItemRestoreController::class)->whereNumber('id')->middleware('can:restore,App\Models\ArchiveItem');
        Route::post('archive-items/{archiveItem}/publish', ArchiveItemPublishController::class)->whereNumber('archiveItem')->middleware('can:publish,archiveItem');

        Route::post('archive-items/{archiveItem}/links', ArchiveItemLinkStoreController::class)->whereNumber('archiveItem')->middleware('can:update,archiveItem');
        Route::delete('archive-items/{archiveItem}/links/{link}', ArchiveItemLinkDestroyController::class)
            ->whereNumber('archiveItem')->whereNumber('link')->middleware('can:update,archiveItem');

        Route::middleware('can:imports.manage')->group(function () {
            Route::get('imports', ImportBatchIndexController::class);
            Route::post('imports', ImportBatchStoreController::class);
            Route::get('imports/{importBatch}', ImportBatchShowController::class);
            Route::get('imports/{importBatch}/rows', ImportBatchRowIndexController::class);
            Route::patch('imports/{importBatch}/rows/{row}', ImportBatchRowUpdateController::class);
            Route::post('imports/{importBatch}/revalidate', ImportBatchRevalidateController::class);
            Route::post('imports/{importBatch}/commit', ImportBatchCommitController::class);
            Route::post('imports/{importBatch}/cancel', ImportBatchCancelController::class);

            Route::get('import-mapping-profiles', ImportMappingProfileIndexController::class);
            Route::post('import-mapping-profiles', ImportMappingProfileStoreController::class);
        });

        Route::get('dashboard/completeness', DashboardCompletenessController::class);
        Route::get('dashboard/records', DashboardRecordsController::class);
        Route::get('dashboard/export', DashboardExportController::class);

        Route::get('records/{type}/{id}/completeness', RecordCompletenessController::class)->whereNumber('id');
        Route::post('records/{type}/{id}/citations', FieldCitationStoreController::class)->whereNumber('id');
        Route::delete('citations/{citation}', FieldCitationDestroyController::class);

        Route::post('source-conflicts/{sourceConflict}/resolve', SourceConflictResolveController::class)
            ->middleware('can:source_conflicts.resolve');

        Route::get('review-queue', ReviewQueueIndexController::class);
        Route::post('review-queue/{reviewQueueItem}/acknowledge', ReviewQueueAcknowledgeController::class);

        Route::middleware('can:artists.manage')->group(function () {
            Route::put('artists/{artist}/entries', ArtistEntriesSyncController::class)->whereNumber('artist');
            Route::put('artists/{artist}/social-links', ArtistSocialLinksSyncController::class)->whereNumber('artist');
            Route::post('artists/{artist}/portrait', [ArtistPortraitController::class, 'store'])->whereNumber('artist');
            Route::patch('artists/{artist}/portrait', [ArtistPortraitController::class, 'update'])->whereNumber('artist');
            Route::delete('artists/{artist}/portrait', [ArtistPortraitController::class, 'destroy'])->whereNumber('artist');
            Route::get('admin/artists', AdminArtistIndexController::class);
            Route::post('artists/merge', ArtistMergeController::class);
            Route::get('artists/{artist}/curation', ArtistCurationShowController::class)->whereNumber('artist');
            Route::patch('artists/{artist}/curation', ArtistCurationUpdateController::class)->whereNumber('artist');
            Route::patch('artists/{artist}/themes', [ThemeController::class, 'sync'])->whereNumber('artist');
            Route::get('themes', [ThemeController::class, 'index']);
            Route::post('themes', [ThemeController::class, 'store']);
        });

        Route::middleware('can:artworks.manage')->group(function () {
            Route::get('admin/artworks', AdminArtworkIndexController::class);
            Route::get('admin/holders', AdminHolderIndexController::class);
            Route::post('artworks/{artwork}/images', [ArtworkImageController::class, 'store'])->whereNumber('artwork');
            Route::patch('artworks/{artwork}/images/{image}', [ArtworkImageController::class, 'update'])->whereNumber(['artwork', 'image']);
            Route::delete('artworks/{artwork}/images/{image}', [ArtworkImageController::class, 'destroy'])->whereNumber(['artwork', 'image']);
            Route::post('artworks/merge', ArtworkMergeController::class);
            Route::post('artworks/{artwork}/approve', ArtworkApproveController::class)->whereNumber('artwork');
            Route::get('candidate-artworks', CandidateArtworkIndexController::class);
            Route::post('candidate-artworks/{candidate}/promote', CandidateArtworkPromoteController::class);
            Route::post('candidate-artworks/{candidate}/dismiss', CandidateArtworkDismissController::class);
            Route::post('pipeline-note-suggestions/{suggestion}/accept', PipelineNoteSuggestionAcceptController::class);
        });
        Route::get('artworks/{artwork}/curation', ArtworkCurationShowController::class)->whereNumber('artwork')->middleware('can:artworks.manage');
        Route::get('artworks/{artwork}/pipeline', ArtworkPipelineShowController::class)->whereNumber('artwork')->middleware('can:artworks.manage');
        Route::patch('artworks/{artwork}/pipeline/{stageKey}', ArtworkPipelineUpdateController::class)->whereNumber('artwork');

        Route::get('activity', ActivityFeedController::class)->middleware('can:activity.view');
        Route::get('{resource}/{id}/activity', SubjectActivityController::class)
            ->whereNumber('id')
            ->middleware('can:activity.view');
    });
});
