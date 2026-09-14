<?php
namespace Tests\Feature;
use App\Models\User;
use App\Notifications\OwnerPasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;
class OwnerAuthTest extends TestCase {
    use RefreshDatabase;
    protected function setUp():void {parent::setUp();config(['locs.mail_enabled'=>true]);}
    private function owner(bool $verified=true): User { $u=User::factory()->create(['email'=>'owner@example.test','password'=>'correct-password-for-tests','email_verified_at'=>$verified?now():null]);$u->forceFill(['is_owner'=>true])->save(); return $u; }
    public function test_admin_requires_independent_login():void { $this->get('/admin')->assertRedirect('/admin/login'); $this->get('/admin/login')->assertOk()->assertSee('Tighten Up Your Locs')->assertHeader('X-Robots-Tag','noindex, nofollow'); }
    public function test_registered_non_owner_is_denied():void { $u=User::factory()->create();$this->actingAs($u)->get('/admin')->assertForbidden(); }
    public function test_correct_owner_password_creates_local_session_and_logout_ends_it():void { $u=$this->owner(); $this->post('/admin/login',['email'=>$u->email,'password'=>'correct-password-for-tests'])->assertRedirect('/admin');$this->assertAuthenticatedAs($u);$this->post('/admin/logout')->assertRedirect('/admin/login');$this->assertGuest(); }
    public function test_wrong_password_and_unactivated_owner_cannot_sign_in():void { $u=$this->owner(false);$this->post('/admin/login',['email'=>$u->email,'password'=>'incorrect'])->assertSessionHasErrors('email');$this->post('/admin/login',['email'=>$u->email,'password'=>'correct-password-for-tests'])->assertSessionHasErrors('email');$this->assertGuest(); }
    public function test_reset_only_notifies_owner_and_does_not_enumerate_users():void { Notification::fake();$u=$this->owner(false);$this->post('/admin/forgot-password',['email'=>$u->email])->assertSessionHas('status');Notification::assertSentTo($u,OwnerPasswordReset::class);$this->post('/admin/forgot-password',['email'=>'absent@example.test'])->assertSessionHas('status');Notification::assertCount(1); }
    public function test_password_setup_is_single_use_and_activates_owner():void { $u=$this->owner(false);$token=Password::createToken($u);$data=['email'=>$u->email,'token'=>$token,'password'=>'new-long-secret-for-tests','password_confirmation'=>'new-long-secret-for-tests'];$this->post('/admin/reset-password',$data)->assertRedirect('/admin/login');$this->assertNotNull($u->fresh()->email_verified_at);$this->post('/admin/reset-password',$data)->assertSessionHasErrors('email'); }
    public function test_public_registration_does_not_exist():void { $this->get('/register')->assertNotFound();$this->post('/register')->assertNotFound(); }
}
