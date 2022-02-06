<?php

namespace App\Http\Controllers;

use Carbon\Carbon;

class BirthdayCakeDaysController extends Controller
{
    // Allowed file types
    public const filetypes = ['txt', 'csv'];

    // Company holidays
    public const companyHolidays = [
        '01-01',
        '12-25',
        '12-26',
    ];

    /**
     * Fetch all info regarding employee birthdays and cakes for the current year from the input file
     *
     * @param $file
     * @return string
     */
    public function fetchBirthdayCakes( $file )
    {
        // Fetch birthday info of all employees from input file
        $employeeBirthday = $this->fetchEmployeeBirthday( $file );

        // Now check if the birthday falls on a holiday or weekend and set the cake day to next working day
        $nextCakeDay = $this->setEmployeeCakeDay( $employeeBirthday );

        // Now we need to skip the employee's birthday ( if birthday is on a working day ) OR the first working day
        // after his/her birthday (if birthday is on a weekend / company holiday)
        $skipEmployeeDayOff = $this->skipEmployeeDayOff( $nextCakeDay );

        // Now fetch the next possible cake day for each employee
        $fetchNextWorkingDay = $this->fetchNextWorkingDay( $skipEmployeeDayOff );

        // Now include cake free days, large or small cakes for each employee
        $fetchCakeDays = $this->fetchCakeDays( $fetchNextWorkingDay );

        // Compile data into CSV and return filepath
        return $this->generateCSV( $fetchCakeDays );
    }

    /**
     * Validate the input file
     *
     * @param $file
     * @return bool
     * @throws \Exception
     */
    public function isValidFile( $file )
    {
        // Ensure file exists
        if( ! file_exists( $file ) ) {
            throw new \Exception( "File '{$file}' not found, please check if the file exists in the location." );
        }

        // Ensure valid extension
        $fileExtn = pathinfo( $file, PATHINFO_EXTENSION );
        if( ! in_array( $fileExtn, self::filetypes ) ) {
            $file_types = implode( ', ',self::filetypes );

            throw new \Exception( "File provided was of type '{$fileExtn}', the allowed file types are {$file_types}." );
        }

        return true;
    }

    /**
     * Extract birthdays from input file
     *
     * @param $file
     * @return array
     * @throws \Exception
     */
    public function fetchEmployeeBirthday( $file )
    {
        $fileArr = file( $file );

        $employeeBirthday = [];

        foreach( $fileArr as $row ) {
            // Ensure the row has specific pattern to rule out csv data issues
            if( ! preg_match( '/((,)([0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])))$/', $row ) ) {
                throw new \Exception( "Data within the row must have the format 'Name,yyyy-mm-dd' - {$row}" );
            }

            // Collect individual row's data
            $rowData  = array_map( 'trim', explode(",", $row ) );
            $employee = $rowData[0];
            $birthday = $rowData[1];

            try {
                $employeeBdy = new Carbon( $birthday ) ;
            }
            catch( \Exception $e ) {
                throw new \Exception( "Invalid date format within the row {$row}" );
            }

            // Convert to current year
            $currentYear = Carbon::now()->format("Y");
            $employeeBdy->setYear( intval( $currentYear ) );
            $employeeBirthday[ $employee ] = $employeeBdy;
        }

        return $employeeBirthday;
    }

    /**
     * If employee's birthday falls on a weekend or a company holiday then cake needs to be send out on next working day
     *
     * @param $employeeBirthday
     * @return array
     */
    public function setEmployeeCakeDay( $employeeBirthday )
    {
        $nextCakeDay = [];

        // If birthday is on a holiday or a weekend keep adding a day each until next day becomes a working day
        foreach( $employeeBirthday as $employee => $date ) {
            while( $date->isWeekend() || in_array( $date->format( 'm-d' ), self::companyHolidays ) ) {
                $date->addDay();
            }

            $nextCakeDay[$employee] = $date;
        }

        return $nextCakeDay;

    }

    /**
     * Employees are given off on their birthday, however if birthday falls on a holiday/ weekend, then they get a day
     * off on the next working day. In either case we need to add an extra day to employee's day off for sending
     * cake since they are meant to receive cakes on the first working day after their birthday day off.
     *
     * @param $cakeDays
     * @return array
     */
    public function skipEmployeeDayOff( $cakeDays ) {
        $skipEmployeeDayOff = [];

        foreach( $cakeDays as $name => $date ) {
            do {
                $date->addDay();
            } while( $date->isWeekend() || in_array( $date->format('m-d'), self::companyHolidays) );

            $skipEmployeeDayOff[$name] = $date;
        }

        return $skipEmployeeDayOff;
    }

    /**
     * Create a list with employees and cake details for each date. The list is grouped by the date so that the CSV
     * generated is shorter and neater.
     *
     * @param $skipEmployeeDayOff
     * @return array
     */
    public function fetchNextWorkingDay( $skipEmployeeDayOff )
    {
        $nextWorkingDay = [];

        // Fetch unique dates
        $dates = array_unique( array_values( $skipEmployeeDayOff ) );

        foreach( $dates as $row ) {
            $formattedDate = $row->format( 'Y-m-d' );

            $nextWorkingDay[$formattedDate]['date'] = $formattedDate;
            $nextWorkingDay[$formattedDate]['cakes'] = 0;
            $nextWorkingDay[$formattedDate]['names'] = '';

            foreach( $skipEmployeeDayOff as $name => $date ) {
                // Concatenate employees sharing birthday cake day
                if( $date == $row ) {
                    $nextWorkingDay[$formattedDate]['names'] = $nextWorkingDay[$formattedDate]['names'] == '' ? $name : $nextWorkingDay[$formattedDate]['names'] . ", " . $name;
                    $nextWorkingDay[$formattedDate]['cakes']++;
                }
            }
        }

        return $nextWorkingDay;
    }

    /**
     * For health reasons, the day after each cake day is cake-free. Any cakes due of a cake-free day is postponed to
     * the next working day.
     *
     * @param $fetchNextWorkingDay
     * @return array
     */
    public function fetchCakeDays( $fetchNextWorkingDay )
    {
        // Get all cake days into an array
        $dates = array_column( $fetchNextWorkingDay, 'date' );

        $cakeDays = [];
        $count = 0;

        // The day after each cake must be cake-free.
        $cakeFreeDays = [];

        foreach( $fetchNextWorkingDay as $cakeDay ) {

            $currentDay = $cakeDay['date'];
            $cakes = $cakeDay['cakes'];

            // Get next day
            $nextDay = ( new Carbon( $cakeDay['date'] ) )->addDay()->format( 'Y-m-d' );

            // If 2 cakes are to be send one after the other, we instead provide one large cake on the second day.
            // Any cakes due on a cake-free day are postponed to the next working day.
            if( in_array( $nextDay, $dates ) && ! in_array( $nextDay, $cakeFreeDays ) ) {
                // Find data object for the next day
                $nextCakeDay = array_search( $nextDay, array_column( $fetchNextWorkingDay, 'date' ) );

                $date  = $nextDay;
                $cakes = $cakeDay['cakes'] + $fetchNextWorkingDay[$nextCakeDay]['cakes'];
                $names = $cakeDay['names'] . ', '.$fetchNextWorkingDay[$nextCakeDay]['names'];

                // The day after each cake must be cake-free.
                $cakeFreeDay = ( new Carbon( $nextDay ) )->addDay()->format( 'Y-m-d' );

            }
            else if( in_array( $currentDay, $cakeFreeDays ) ) {
                // Any cakes due on a cake-free day are postponed to the next working day.
                $skipDay = new Carbon( $currentDay );
                do {
                    $skipDay->addDay();
                } while( $skipDay->isWeekend() || in_array( $skipDay->format( 'm-d' ), self::companyHolidays ) );

                $date = $skipDay->format( 'Y-m-d' );
                $names = $cakeDay['names'];

                // The day after each cake must be cake-free.
                $cakeFreeDay = $skipDay->addDay()->format( 'Y-m-d' );
            }
            else {
                // If the current cake day date has already been processed, then skip
                if( array_search( $cakeDay['date'], array_column( $cakeDays, 'date' ) ) !== false )
                {
                    continue;
                }

                $date = $cakeDay['date'];
                $names = $cakeDay['names'];

                // The day after each cake must be cake-free.
                $cakeFreeDay = ( new Carbon( $cakeDay['date'] ) )->addDay()->format( 'Y-m-d' );
            }

            // Assign values to new array item
            $cakeDays[$count]['date'] = $date;
            $cakeDays[$count]['cakes'] = $cakes;
            $cakeDays[$count]['names'] = $names;

            $count++;

            $cakeFreeDays[] = $cakeFreeDay;

        }

        usort( $cakeDays, array( $this,'sortDates' ) );

        return $cakeDays;
    }

    /**
     * Sort dates callback function
     *
     * @param $a
     * @param $b
     * @return false|int
     */
    private function sortDates( $a, $b )
    {
        return strtotime( $a['date'] ) - strtotime( $b['date'] );
    }

    /**
     * Generate CSV with all info and send over csv filename
     *
     * @param $data
     * @return string
     * @throws \Exception
     */
    public function generateCSV( $data )
    {
        $date = ( new Carbon() )->format('d-m-Y' );

        // CSV headers
        $headers = [ 'Date', 'Number of Small Cakes', 'Number of Large Cakes', 'Names of people getting cakes' ];

        $filename = "EmpBdyCakes-".$date.".csv";

        try {
            $file = fopen( $filename, 'w' );

            fputcsv( $file, $headers );

            foreach( $data as $row ) {

                $csvRow['date'] = $row['date'];

                //If two or more cakes days coincide, we instead provide one large cake to share.
                if( $row['cakes'] > 1 ) {
                    $csvRow['small_cakes'] = 0;
                    $csvRow['large_cakes'] = 1;
                }
                else {
                    $csvRow['small_cakes'] = 1;
                    $csvRow['large_cakes'] = 0;
                }

                $csvRow['names'] = $row['names'];

                fputcsv( $file, $csvRow );
            }

            fclose( $file );
        }
        catch( \Exception $e ) {
            throw new \Exception( "Unable to generate a CSV due to the following error: {$e}" );
        }

        return $filename;
    }
}
