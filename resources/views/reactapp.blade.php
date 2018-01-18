@extends('layouts.reactapp')
@section('content')
<!--S home -->
<div id="root"></div><!-- ReactDOM.render() should render into content -->
<!--E home -->
@endsection
@section('javascript')
<script type="text/javascript" src="{{ asset("FreeMaths.js") }}"></script>
@endsection
