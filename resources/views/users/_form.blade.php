<section class="card mb-3" aria-labelledby="sec-person">
    <div class="panel-header"><h2 id="sec-person" class="panel-title"><span class="step-number">1</span> Thông tin người dùng</h2></div>
    <div class="card-body pb-1">
        <div class="row">
            <div class="col-md-6"><x-input name="name" label="Họ tên" :value="$user->name" required maxlength="255" /></div>
            <div class="col-md-6"><x-input name="email" label="Email đăng nhập" type="email" :value="$user->email" required maxlength="255" autocomplete="off" /></div>
        </div>
    </div>
</section>

<section class="card mb-3" aria-labelledby="sec-role">
    <div class="panel-header"><h2 id="sec-role" class="panel-title"><span class="step-number">2</span> Vai trò</h2></div>
    <div class="card-body">
        <fieldset>
            <legend class="visually-hidden">Vai trò <span>(bắt buộc)</span></legend>
            @foreach(App\Enums\Role::cases() as $r)
                <label class="choice-card" for="role_{{ $r->value }}">
                    <input class="form-check-input @error('role') is-invalid @enderror" type="radio" name="role" id="role_{{ $r->value }}" value="{{ $r->value }}"
                           @checked(old('role', $user->role?->value) === $r->value)>
                    <span>
                        <strong class="d-block">{{ $r->label() }}</strong>
                        <span class="small text-body-secondary">{{ $r->description() }}</span>
                    </span>
                </label>
            @endforeach
            @error('role')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </fieldset>
    </div>
</section>

<section class="card mb-3" aria-labelledby="sec-pass">
    <div class="panel-header"><h2 id="sec-pass" class="panel-title"><span class="step-number">3</span> {{ $user->exists ? 'Đặt lại mật khẩu' : 'Mật khẩu' }}</h2></div>
    <div class="card-body pb-1">
        <div class="row">
            <div class="col-md-6">
                <x-input name="password" label="{{ $user->exists ? 'Mật khẩu mới' : 'Mật khẩu' }}" type="password" :required="! $user->exists" autocomplete="new-password"
                         help="{{ $user->exists ? 'Để trống nếu không đổi. ' : '' }}Tối thiểu 8 ký tự." />
            </div>
            <div class="col-md-6">
                <x-input name="password_confirmation" label="Nhập lại mật khẩu" type="password" :required="! $user->exists" autocomplete="new-password" />
            </div>
        </div>
        <div class="callout callout-muted mb-3"><i class="bi bi-shield-lock" aria-hidden="true"></i>
            <span>Gửi mật khẩu riêng cho người dùng và nhắc họ đổi sau khi đăng nhập. Hệ thống không lưu hay hiển thị lại mật khẩu.</span></div>
    </div>
</section>
