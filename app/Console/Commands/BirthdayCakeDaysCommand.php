<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Helper\Table;

use App\Http\Controllers\BirthdayCakeDaysController;

class BirthdayCakeDaysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'output:birthday_cake_days {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get CSV output with details of employee birthday cakes for the current year';

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
     * Return the command arguments
     *
     * @return array[]
     */
    protected function getArguments()
    {
        return [
            [ 'file', InputArgument::REQUIRED, 'File path' ]
        ];
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $file          = $this->argument( 'file' );
            $birthdayCakes = new BirthdayCakeDaysController();
            $isValid       = $birthdayCakes->isValidFile( $file );

            // Proceed only if input file passes validations
            if ( $isValid ) {
                $this->line( "----- Employee Birthday Cakes -----" );
                $this->newLine();

                // Set the table contents
                $cakes = $birthdayCakes->fetchBirthdayCakes( $file );

                if ( empty( $cakes ) ) {
                    $this->info( 'No data to show' );
                } else {
                    $this->info( "A CSV file with the information below has been generated and stored at: " . realpath( $cakes ) );
                    $this->newLine();

                    // Create a new Table instance
                    $table = new Table( $this->output );

                    // Set the table headers
                    $table->setHeaders( [
                        'Date', 'Number of Small Cakes', 'Number of Large Cakes', 'Names of people getting cakes'
                    ] );

                    // Read file content
                    $cakesData = file( $cakes );

                    // Remove first item from array since this is the CSV column headers
                    array_shift( $cakesData );

                    foreach( $cakesData as $row ) {
                        $row = str_getcsv( $row, ",", '"');
                        $table->addRow( $row );
                    }

                    // Render the table to the console output
                    $table->render();
                }
            }
        }
        catch( \Exception $e ) {
            $this->error( 'Exception: ' . $e->getMessage() );
        }

        return 0;
    }
}
