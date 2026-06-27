<?php

namespace Tests\Feature\Manage;

use App\Modules\Manage\Enums\ExpenseCategory;
use App\Modules\Manage\Enums\IncidentStatus;
use App\Modules\Manage\Models\Expense;
use App\Modules\Manage\Models\Incident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests du socle de données incidents & dépenses (phase B4.3).
 */
class IncidentExpenseModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_incident_est_lie_a_un_bien(): void
    {
        $incident = Incident::factory()->create();

        $this->assertNotNull($incident->property);
        $this->assertSame(IncidentStatus::OUVERT, $incident->status);
    }

    public function test_un_incident_resolu_est_horodate(): void
    {
        $incident = Incident::factory()->resolved()->create();

        $this->assertSame(IncidentStatus::RESOLU, $incident->status);
        $this->assertNotNull($incident->resolved_at);
    }

    public function test_une_depense_est_liee_a_un_bien_et_eventuellement_a_un_incident(): void
    {
        $incident = Incident::factory()->create();
        $expense = Expense::factory()->create([
            'property_id' => $incident->property_id,
            'incident_id' => $incident->id,
            'category' => ExpenseCategory::REPARATION->value,
        ]);

        $this->assertTrue($expense->property->is($incident->property));
        $this->assertTrue($expense->incident->is($incident));
        $this->assertSame(ExpenseCategory::REPARATION, $expense->category);
    }
}
