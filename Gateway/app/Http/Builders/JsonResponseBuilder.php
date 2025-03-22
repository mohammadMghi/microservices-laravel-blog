<?php

namespace App\Http\Builders;
 
   
use App\Http\Builders\Contracts\ResponseBuilderInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Routing\ResponseFactory;

class JsonResponseBuilder implements ResponseBuilderInterface
{
    protected ResponseFactory $factory;

    /**
     * @param ResponseFactory $factory
     */
    public function __construct(ResponseFactory $factory) 
    {
        $this->factory = $factory;
    }


    public function build($data, int $status, string $code, string $message, $header = [], $meta = [])
    {
        $generatedData = [
            'message' => $message,
            'code' => $code,
            'data' => $data,
        ];

        if(!empty($meta)){
            $generatedData['meta'] = $meta;
        }

        if(isset($data->resource) && $data->resource instanceof LengthAwarePaginator){
            if(empty($generatedData['meta'])){
                $generatedData['meta'] = [];
            }
            $paginatedData = $data->resource;
            $generatedData['meta'] = array_merge($generatedData['meta'], [
                'pagination' => [
                    'from' => (int) $paginatedData->firstItem(),
                    'to' => (int) $paginatedData->lastItem(),
                    'current_page' => (int) $paginatedData->currentPage(),
                    'last_page' => (int) $paginatedData->lastPage(),
                    'per_page' => (int) $paginatedData->perPage(),
                    'total' => (int) $paginatedData->total(),
                ]
            ]);
        }



        return $this->factory->make(
            $generatedData,
            $status,
            $header
        );
    }
}
