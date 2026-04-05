<?php

namespace YasserElgammal\Green\Http;

class JsonResponse extends Response
{
    public function __construct(mixed $data = null, int $status = 200, array $headers = [])
    {
        parent::__construct('', $status, $headers);
        $this->setHeader('Content-Type', 'application/json');
        if ($data !== null) {
            $this->setData($data);
        }
    }

    public function setData(mixed $data): static
    {
        $this->content = json_encode($data);
        return $this;
    }
}
