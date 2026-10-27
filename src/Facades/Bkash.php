<?php

namespace Msilabs\Bkash\Facades;

use Illuminate\Support\Facades\Facade;
use Msilabs\Bkash\BkashClient;
use Msilabs\Bkash\BkashManager;

/**
 * @method static \Msilabs\Bkash\BkashClient account(?string $name = null)
 * @method static \Msilabs\Bkash\BkashClient using(array $credentials, string $name = 'custom')
 * @method static string getDefaultAccount()
 * @method static array accounts()
 * @method static void purge(?string $name = null)
 * @method static \Msilabs\Bkash\BkashResponse createPayment(string|int|float $amount, string $invoiceNumber, string $callbackUrl, ?string $payerReference = null, array $extra = [])
 * @method static \Msilabs\Bkash\BkashResponse executePayment(string $paymentId)
 * @method static \Msilabs\Bkash\BkashResponse queryPayment(string $paymentId)
 * @method static \Msilabs\Bkash\BkashResponse searchTransaction(string $trxId)
 * @method static \Msilabs\Bkash\BkashResponse refund(string $paymentId, string $trxId, string|int|float $amount, string $reason, string $sku = 'N/A')
 * @method static \Msilabs\Bkash\BkashResponse refundStatus(string $paymentId, string $trxId)
 * @method static \Msilabs\Bkash\BkashResponse createAgreement(string $payerReference, string $callbackUrl)
 * @method static \Msilabs\Bkash\BkashResponse executeAgreement(string $agreementId)
 * @method static \Msilabs\Bkash\BkashResponse queryAgreement(string $agreementId)
 * @method static \Msilabs\Bkash\BkashResponse cancelAgreement(string $agreementId)
 * @method static \Msilabs\Bkash\BkashResponse createPaymentWithAgreement(string $agreementId, string|int|float $amount, string $invoiceNumber, string $callbackUrl, array $extra = [])
 * @method static \Msilabs\Bkash\BkashResponse executePaymentWithAgreement(string $paymentId, string $agreementId)
 * @method static string token(bool $fresh = false)
 * @method static void forgetToken()
 * @method static bool isSandbox()
 * @method static string baseUrl()
 *
 * @see BkashManager
 * @see BkashClient
 */
class Bkash extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BkashManager::class;
    }
}
