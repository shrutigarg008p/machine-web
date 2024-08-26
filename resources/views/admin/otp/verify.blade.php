@extends('layouts.admin')
@section('content')
<div class="container">
   <div class="row justify-content-center">
      <div class="col-md-8">
         <div class="card">
            <div class="card-header">{{ __('Please verify code from your phone number to active account') }}</div>
            <div class="card-body">
               @if ($message = Session::get('success'))
               <div class="alert alert-success alert-block">
                  <button type="button" class="close" data-dismiss="alert">×</button>
                  <strong>{{ $message }}</strong>
               </div>
               @endif
               @if ($message = Session::get('error'))
               <div class="alert alert-danger alert-block">
                  <button type="button" class="close" data-dismiss="alert">×</button>
                  <strong>{{ $message }}</strong>
               </div>
               @endif 
               <form method="POST" action="{{ route('admin.verify') }}">
                  @csrf
                  <div class="form-group row">
                     <label for="password" class="col-md-4 col-form-label text-md-right">{{ __('code') }}</label>
                     <div class="col-md-6">
                        <input id="code" type="text" class="form-control{{ $errors->has('otp') ? ' is-invalid' : '' }}" name="otp" required>
                        @if ($errors->has('code'))
                        <span class="invalid-feedback" role="alert">
                        <strong>{{ $errors->first('otp') }}</strong>
                        </span>
                        @endif
                     </div>
                  </div>
                  <div class="form-group row mb-0">
                     <div class="col-md-8 offset-md-4">
                        <button type="submit" class="btn btn-primary">
                        {{ __('Verify') }}
                        </button>
                     </div>
                  </div>
               </form>
            </div>
            <div class="card-footer">
               {{--  <a href="">Request new code</a>
               <input type="hidden" name="phone" value="{{request()->phone}}"> --}}
            </div>
         </div>
      </div>
   </div>
</div>
@endsection