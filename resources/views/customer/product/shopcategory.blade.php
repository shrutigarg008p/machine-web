@extends('customer.layouts.main')

@push('custom_css')
@endpush

@section('content')
    <section class="tabs-wrapper">
        <div class="container">
            <h1 class="main-title-tabs">Shop Categories</h1>
            <!-- Nav pills -->
            <ul class="nav nav-pills nav-pill-custom" role="tablist">
               @if(isset($categories['data']))
                @php $i =0; @endphp
                @forelse ($categories['data'] as $cat)
                @php $i++; @endphp
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $i==1?'active':''}}" data-bs-toggle="pill" href="#{{ $cat['title'] }}" aria-selected="true"
                            role="tab">{{ $cat['title'] }}</a>
                    </li>
                @empty
                @php $i++; @endphp
                @endforelse
              @endif
            </ul>

            <!-- Tab panes -->
            <div class="tab-content tab-content-custom">
            @if(isset($categories['data']))
                @php $i =0; @endphp
                @forelse($categories['data'] as $cat)
                    @php $i++; @endphp
                    <div id="{{ $cat['title'] }}" class="container tab-pane fade {{ $i==1?'active show':''}}" role="tabpanel"><br>
                        @php
                            $result = App\Traits\APICall::callAPI(
                                'POST',config('app.url') .
                                App\Http\Controllers\Customer\EndPoints::SHOP_SEARCH,
                                json_encode([
                                    'category' => $cat['id'],
                                    'lat' => $lat,
                                    'lng' => $lng,
                                    'shoptitle' => $shoptitle,
                                    'userId' => Auth::user()->id,
                                ]),
                            );
                            $shopbycat = json_decode($result, true);
                            @endphp
                            
                        <div class="row">
                            @forelse ($shopbycat['data'] as $shop)
                                <div class="col-lg-3 col-md-4 col-12">
                                    <div class="electrical-box-wrapper">
                                        <div class="electrical-item">
                                            <a href="{{ route('shopdetails',['id'=>$shop['id']]) }}">
                                                <img src="{{ $shop['shop_logo']}}"
                                                    alt="item-1" class="img-fluid">
                                            </a>

                                            <span class="offer-box">{{ $shop['offer'] }}</span>
                                            <span class="rating-box"><span class="fa fa-star checked"></span>{{ $shop['rating'] }}</span>
                                        </div>
                                        <div class="electrical-item-content">
                                            <div class="electrical-item-title">
                                                <a href="{{ route('shopdetails',['id'=>$shop['id']]) }}">{{ $shop['shop_name'] }}</a>
                                                <span>
                                                    <a href="{{ route('shopdetails',['id'=>$shop['id']]) }}">
                                                        <img src="{{ asset('web/images/electrical/export.png') }}"
                                                            alt=""></a>
                                                </span>
                                            </div>
                                            <div class="electrical-item-subtitle">
                                                <div class="map-wrapper">
                                                    <img src="{{ asset('web/images/electrical/pin.png') }}" alt="map"
                                                        class="img-fluid" width="20px">

                                                </div>
                                                <div class="location-wrapper-txt">
                                                    <span class="green-txt">{{ $shop['distance'].' '.$shop['distance_unit']}}</span> |
                                                    <span class="location-txt">
                                                       {{ $shop['address']}}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="clockbox">
                                                <div class="clock-wrapper">
                                                    <img src="{{ asset('web/images/electrical/clock.png') }}" alt="map"
                                                        class="img-fluid" width="16px">
                                                </div>
                                                <div class="clock-wrapper-txt">
                                                    Working Hours: {{ $shop['working_hours'] }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                            <div class="col-12">
                                <div class="electrical-box-wrapper m-5 text-center">
                                    <h4>No Data Found</h4>
                                        
                                </div>
                            </div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    @php $i++; @endphp
                @endforelse
            @endif
            </div>
        </div>
    </section>
@endsection
