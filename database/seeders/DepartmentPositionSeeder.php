<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

class DepartmentPositionSeeder extends Seeder
{
    private array $departments = [
        ['name' => 'Management', 'code' => 'MGMT', 'description' => 'Company management and board of directors'],
        ['name' => 'Sales', 'code' => 'SAL', 'description' => 'Sales department'],
        ['name' => 'Marketing', 'code' => 'MKT', 'description' => 'Marketing department'],
        ['name' => 'Technical Sales Support', 'code' => 'TSS', 'description' => 'Technical Sales Support department'],
        ['name' => 'Finance & Accounting', 'code' => 'FNA', 'description' => 'Finance and Accounting department'],
        ['name' => 'Import & Purchasing', 'code' => 'IPC', 'description' => 'Import and Purchasing department'],
        ['name' => 'Regulatory & Quality Assurance', 'code' => 'RQA', 'description' => 'Regulatory and Quality Assurance department'],
        ['name' => 'Logistics', 'code' => 'LOG', 'description' => 'Logistics department'],
    ];

    private array $positions = [
        // Management
        'MGMT' => [
            ['name' => 'Managing Director', 'level' => 0, 'code' => 'MD'],
            ['name' => 'Director', 'level' => 1, 'code' => 'DIR'],
        ],
        // Marketing
        'MKT' => [
            ['name' => 'Sr Product Manager', 'level' => 1, 'code' => 'SPM'],
            ['name' => 'Jr Product Manager', 'level' => 2, 'code' => 'JPM'],
            ['name' => 'Product Executive', 'level' => 3, 'code' => 'PEX'],
        ],
        // Sales
        'SAL' => [
            ['name' => 'Regional Sales Manager', 'level' => 1, 'code' => 'RSM'],
            ['name' => 'Area Sales Manager', 'level' => 2, 'code' => 'ASM'],
            ['name' => 'Sales Representative', 'level' => 3, 'code' => 'SRP'],
        ],
        // Technical Sales Support
        'TSS' => [
            ['name' => 'Field Service Engineer Manager', 'level' => 1, 'code' => 'FSEM'],
            ['name' => 'Application Specialist Supervisor', 'level' => 2, 'code' => 'ASS'],
            ['name' => 'Field Service Engineer Supervisor', 'level' => 2, 'code' => 'FSES'],
            ['name' => 'Application Specialist Staff', 'level' => 3, 'code' => 'ASST'],
            ['name' => 'Field Service Engineer Staff', 'level' => 3, 'code' => 'FSESF'],
        ],
        // Finance & Accounting
        'FNA' => [
            ['name' => 'Finance & Accounting Supervisor', 'level' => 1, 'code' => 'FAS'],
            ['name' => 'Invoice Staff', 'level' => 2, 'code' => 'INV'],
        ],
        // Import & Purchasing
        'IPC' => [
            ['name' => 'Import & Purchasing Supervisor', 'level' => 1, 'code' => 'IPS'],
        ],
        // Regulatory & Quality Assurance
        'RQA' => [
            ['name' => 'Quality Assurance Staff', 'level' => 1, 'code' => 'QAS'],
            ['name' => 'Quality Control Staff', 'level' => 1, 'code' => 'QCS'],
        ],
        // Logistics
        'LOG' => [
            ['name' => 'Logistic Staff', 'level' => 1, 'code' => 'LST'],
            ['name' => 'Logistic Operational Administration Staff', 'level' => 1, 'code' => 'LOAS'],
        ],
    ];

    public function run(): void
    {
        $departmentMap = [];

        // Create departments
        foreach ($this->departments as $dept) {
            $department = Department::firstOrCreate(
                ['code' => $dept['code']],
                $dept
            );
            $departmentMap[$dept['code']] = $department->id;
        }

        // Create positions
        foreach ($this->positions as $deptCode => $deptPositions) {
            $departmentId = $departmentMap[$deptCode];
            $positionsByLevel = [];

            foreach ($deptPositions as $position) {
                $pos = Position::firstOrCreate(
                    ['code' => $position['code']],
                    [
                        'name' => $position['name'],
                        'level' => $position['level'],
                        'department_id' => $departmentId,
                        'description' => $position['name'],
                    ]
                );

                if (! isset($positionsByLevel[$position['level']])) {
                    $positionsByLevel[$position['level']] = [];
                }
                $positionsByLevel[$position['level']][] = $pos->id;
            }

            // Set parent relationships (all positions at level N report to first position at level N-1)
            $levels = array_keys($positionsByLevel);
            sort($levels);

            foreach ($levels as $index => $level) {
                if ($index > 0) {
                    $parentLevel = $levels[$index - 1];
                    $parentId = $positionsByLevel[$parentLevel][0];

                    foreach ($positionsByLevel[$level] as $positionId) {
                        Position::where('id', $positionId)
                            ->update(['parent_id' => $parentId]);
                    }
                }
            }
        }
    }
}
