@extends('errors.layout')
@section('code', '403')
@section('title', 'Bạn không có quyền truy cập')
@section('message')
@php($msg = $exception->getMessage())
{{ $msg && $msg !== 'This action is unauthorized.' ? $msg : 'Tài khoản của bạn không có quyền thực hiện thao tác này. Nếu cần, hãy liên hệ điều phối viên hoặc quản trị viên.' }}
@endsection
