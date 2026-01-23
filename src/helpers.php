<?php

if (! function_exists('registerAction')) {
    function registerAction(string $action, $requester = null, array $extra = []): void
    {
        $monitor = app(\ChrisHenrique\RequestsMonitor\Contracts\RequestsMonitor::class);

        $requesterData = [];
        if ($requester instanceof \Illuminate\Database\Eloquent\Model) {
            $requesterData = [
                'requester_type' => get_class($requester),
                'requester_id' => $requester->getKey(),
            ];
        }
        $requesterData = array_merge([
            'action_name' => $action,
            'method' => 'EVENT',
            'url' => $action,
            'content' => $extra,
        ], $requesterData);

        $monitor->logManually($requesterData);
    }
}
