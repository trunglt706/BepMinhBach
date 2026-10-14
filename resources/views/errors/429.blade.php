@extends('errors.layout')
@section('code', '429')
@section('title', 'Thao tác quá nhanh')
@section('message')
Bạn đã thao tác quá nhiều lần trong thời gian ngắn.
@isset($retryAfter) Vui lòng đợi khoảng {{ $retryAfter }} giây rồi tải lại trang. @else Vui lòng đợi một chút rồi thử lại. @endisset
@endsection
