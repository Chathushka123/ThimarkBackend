<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateStubs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stub:generate {model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate skeleton code for Model, Migration, Resource and Controller';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $model = $this->argument('model');

        $this->info('Generating skeletons for ' . $model . "...");
        $this->info("");

        // $bar = $this->output->createProgressBar(3);
        // $bar->start();

        // create Model and migration files
        $exitCode = $this->callSilent('make:model', ['name' => $model, '--migration' => true]);
        // $bar->advance();

        // create Resource file
        $exitCode = $this->callSilent('make:resource', ['name' => $model . 'Resource']);
        // $bar->advance();

        // create Controller
        $exitCode = $this->callSilent('make:controller', ['name' => 'Api/' . $model . 'Controller', '--api' => true, '--model' => $model]);
        // $bar->finish();

        $this->info(">> Model, Migration, Resource and Controller file were generated.");
        $this->info("");
        $this->info("Please add below lines to routes\api.php");
        $this->info("");

        $plural = Str::plural($model);
        $text = "Route::get('" . Str::camel($plural) . "/{" . Str::camel($model) . "}', 'Api\\" . Str::ucfirst($model) . "Controller@show')->name('" . Str::camel($plural) . ".show');";
        $this->info($text);
        $text = "Route::get('" . Str::camel($plural) . "', 'Api\\" . Str::ucfirst($model) . "Controller@index')->name('" . Str::camel($plural) . ".index');";
        $this->info($text);
        $text = "Route::post('" . Str::camel($plural) . "', 'Api\\" . Str::ucfirst($model) . "Controller@store')->name('" . Str::camel($plural) . ".store');";
        $this->info($text);
        $text = "Route::put('" . Str::camel($plural) . "', 'Api\\" . Str::ucfirst($model) . "Controller@update')->name('" . Str::camel($plural) . ".update');";
        $this->info($text);
        $text = "Route::delete('" . Str::camel($plural) . "', 'Api\\" . Str::ucfirst($model) . "Controller@destroy')->name('" . Str::camel($plural) . ".destroy');";
        $this->info($text);
    }
}
