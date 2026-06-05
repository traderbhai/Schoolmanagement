@extends('layouts.app')
@section('content')
<div class="container">
    <h1>Fee Demand</h1>
    <p>Student: {{ $feeDemand->student->name }}</p>
    <p>Amount: {{ $feeDemand->final_amount }}</p>
    <p>Status: {{ $feeDemand->status }}</p>
</div>
@endsection
