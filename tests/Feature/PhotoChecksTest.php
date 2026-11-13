<?php

namespace Tests\Feature;

use App\Models\FinancialEntry;
use App\Models\RentalItem;
use App\Models\User;
use App\Services\ReceiptChecks;
use App\Support\PhotoFingerprint;
use Carbon\CarbonImmutable;
use Database\Seeders\BookingsSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoChecksTest extends TestCase
{
    use RefreshDatabase;

    private function example(string $name): string
    {
        return base_path("exemplos/comprovantes/{$name}");
    }

    private function resavedCopy(string $path): string
    {
        $image = imagecreatefromjpeg($path);
        $copy = imagescale($image, (int) (imagesx($image) * 0.6));
        $target = tempnam(sys_get_temp_dir(), 'copy').'.jpg';
        imagejpeg($copy, $target, 70);

        return $target;
    }

    public function test_the_same_picture_resaved_is_recognised_and_a_different_one_is_not(): void
    {
        $original = PhotoFingerprint::of($this->example('01-cupom-posto.jpg'));

        $this->assertLessThanOrEqual(PhotoFingerprint::SAME_PHOTO, PhotoFingerprint::distance($original, PhotoFingerprint::of($this->resavedCopy($this->example('01-cupom-posto.jpg')))));
        $this->assertGreaterThan(PhotoFingerprint::SAME_PHOTO, PhotoFingerprint::distance($original, PhotoFingerprint::of($this->example('03-pedido-ferragens.jpg'))));
    }

    public function test_receipt_checks(): void
    {
        $today = CarbonImmutable::parse('2026-11-12');
        $checks = app(ReceiptChecks::class);
        $read = ['supplier_cnpj' => '48.594.624/0001-00', 'date' => '2026-11-06', 'amount' => 340.0, 'subtotal_as_printed' => '345,21', 'discount_as_printed' => '5,21'];

        $this->assertSame([], $checks->check($read, [], $today));

        $codes = fn (array $result) => array_column($result, 'code');

        $this->assertSame(['invalid_cnpj'], $codes($checks->check(['supplier_cnpj' => '48.594.624/0001-09'] + $read, [], $today)));
        $this->assertSame(['future_date'], $codes($checks->check(['date' => '2026-11-20'] + $read, [], $today)));
        $this->assertSame(['totals_mismatch'], $codes($checks->check(['amount' => 840.0] + $read, [], $today)));
        $this->assertSame(['duplicate_photo'], $codes($checks->check($read, ['no lançamento "Diesel", de 06/11/2026'], $today)));

        FinancialEntry::create(['type' => 'expense', 'category' => 'Combustível e transporte', 'description' => 'Diesel', 'amount' => 340, 'due_date' => '2026-11-06']);
        $this->assertSame(['duplicate_entry'], $codes($checks->check($read, [], $today)));
    }

    public function test_a_return_photo_that_is_the_checkout_photo_again_is_flagged(): void
    {
        Storage::fake('public');
        Queue::fake();
        BookingsSeeder::$referenceDate = CarbonImmutable::today();
        $this->seed([CatalogSeeder::class, BookingsSeeder::class]);
        $this->actingAs(User::factory()->create());

        $item = RentalItem::first();
        $photo = fn () => new UploadedFile($this->example('02-recibo-oficina.jpg'), 'foto.jpg', 'image/jpeg', null, true);

        $this->post("/api/rental-items/{$item->id}/checkout-photo", ['photo' => $photo()], ['Accept' => 'application/json'])
            ->assertSuccessful()->assertJsonPath('checks', []);

        $this->post("/api/rental-items/{$item->id}/return-photo", ['photo' => $photo()], ['Accept' => 'application/json'])
            ->assertSuccessful()->assertJsonPath('checks.0.code', 'same_as_checkout');

        $other = RentalItem::whereKeyNot($item->id)->first();
        $this->post("/api/rental-items/{$other->id}/checkout-photo", ['photo' => $photo()], ['Accept' => 'application/json'])
            ->assertSuccessful()->assertJsonPath('checks.0.code', 'duplicate_photo');
    }
}
