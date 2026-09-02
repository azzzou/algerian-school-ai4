<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Api\MessengerWebhookController;
use App\Http\Controllers\Api\HealthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// ---------------------------------------------------------------
// Health check (no auth required)
// ---------------------------------------------------------------
Route::get('/health', [HealthController::class, 'index']);

// ---------------------------------------------------------------
// Facebook Messenger Webhook
// ---------------------------------------------------------------
Route::get('/webhook', [MessengerWebhookController::class, 'verify']);

Route::post('/webhook', [MessengerWebhookController::class, 'handleWebhook']);

// ---------------------------------------------------------------
// Facebook Comments Webhook
// ---------------------------------------------------------------
Route::post('/webhook/facebook/comments', 'Api\FacebookCommentController@handleComment')
    ->name('webhook.facebook.comments');

// ---------------------------------------------------------------
// AI Leads API â€” secured with X-API-Key header
// ---------------------------------------------------------------
Route::prefix('leads')->middleware('ai.api_key')->group(function () {

    // POST /api/leads â€” store a single lead
    Route::post('/', 'Api\LeadApiController@store')->name('api.leads.store');

    // POST /api/leads/batch â€” store multiple leads
    Route::post('/batch', 'Api\LeadApiController@storeBatch')->name('api.leads.batch');

    // GET /api/leads â€” list leads (with optional filters)
    Route::get('/', 'Api\LeadApiController@index')->name('api.leads.index');

    // GET /api/leads/{lead_id} â€” get a single lead
    Route::get('/{lead_id}', 'Api\LeadApiController@show')->name('api.leads.show');
});





