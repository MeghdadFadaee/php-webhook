<?php

class MainController
{
    public function __invoke(Request $request): void
    {
        if (! $request->has('token')) {
            return;
        }

        $data = $request->validate([
            'token' => 'required',
        ]);


        JsonResponse::successful('Controlled!')->exit();
    }
}