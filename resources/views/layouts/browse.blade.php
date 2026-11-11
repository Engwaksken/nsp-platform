{{--
    Pages that are public but also part of the member area (opportunities, directory, jobs, marketplace,
    Knowledge Hub, challenges): signed-in members see them inside their own shell (sidebar and top bar,
    no public header or footer); visitors see the public site layout. Pages extend this exactly as they
    would extend layouts.public, and every variable passed here reaches the chosen layout.
--}}
@extends(auth()->check() ? 'layouts.app' : 'layouts.public')
