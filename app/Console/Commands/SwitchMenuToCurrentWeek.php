<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Menu;
use App\Models\ChosenMenu;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SwitchMenuToCurrentWeek extends Command
{
    protected $signature = 'menu:switch-to-current-week 
                            {--force : Force switch even if current week has menus}
                            {--keep-next : Keep next week menus after copying}
                            {--dry-run : Preview changes without executing}';
    
    protected $description = 'ADMIN ONLY: Copy next week\'s menu options to current week when current week is empty';
    
    protected $adminUser = null;
    protected $imageCopies = [];
    protected $imageErrors = [];
    protected $optionCounters = [];

    public function handle()
    {
        $this->showBanner();
        
        // 1. Authenticate admin user
        $this->adminUser = $this->authenticateAdmin();
        
        if (!$this->adminUser) {
            $this->error("Authentication failed. Command aborted.");
            return 1;
        }
        
        $this->showAdminInfo();
        
        // 2. Determine week boundaries (Monday-Thursday)
        $currentWeekRange = $this->getCurrentWeekRange();
        $nextWeekRange = $this->getNextWeekRange();
        
        // Get week identifiers for folder names
        $currentWeekFolder = $this->getWeekFolderName($currentWeekRange['monday']);
        $nextWeekFolder = $this->getWeekFolderName($nextWeekRange['monday']);
        
        $this->showWeekInfo($currentWeekRange, $nextWeekRange, $currentWeekFolder, $nextWeekFolder);
        
        // 3. Check current week menus
        $currentMenus = Menu::whereBetween('menu_date', [
            $currentWeekRange['start'],
            $currentWeekRange['end']
        ])->get();
        
        if ($currentMenus->count() > 0 && !$this->option('force')) {
            $this->showCurrentWeekError($currentMenus);
            return 1;
        }
        
        // 4. Check next week menus
        $nextWeekMenus = Menu::whereBetween('menu_date', [
            $nextWeekRange['start'],
            $nextWeekRange['end']
        ])->orderBy('menu_date')->orderBy('catering')->get();
        
        if ($nextWeekMenus->isEmpty()) {
            $this->showNextWeekError();
            return 1;
        }
        
        $this->info("✅ Found {$nextWeekMenus->count()} menu(s) for next week");
        
        // 5. Check image files and prepare for copying
        $this->checkAndPrepareImages($nextWeekMenus, $nextWeekFolder, $currentWeekFolder);
        
        // 6. Show detailed preview including image info
        $this->showPreview($nextWeekMenus, $currentMenus, $nextWeekFolder, $currentWeekFolder);
        
        // 7. Show warnings if needed
        $this->showWarnings($nextWeekMenus, $currentMenus);
        
        // 8. Dry run option
        if ($this->option('dry-run')) {
            $this->showDryRunComplete();
            return 0;
        }
        
        // 9. Confirmation (unless --no-interaction)
        if (!$this->confirmExecution($nextWeekMenus, $currentMenus)) {
            return 0;
        }
        
        // 10. Create current week image folders for each day WITH ORIGINAL USERNAMES
        $foldersCreated = $this->createImageFolders($currentWeekFolder, $nextWeekMenus);
        if (!$foldersCreated) {
            $this->error("Failed to create image folders for week {$currentWeekFolder}");
            return 1;
        }
        
        // 11. Execute the switch (including image copying)
        $result = $this->executeSwitch($nextWeekMenus, $currentWeekRange, $currentWeekFolder, $nextWeekFolder);
        
        if (!$result['success']) {
            $this->error("Operation failed: " . $result['message']);
            return 1;
        }
        
        // 12. Show image copy results
        $this->showImageCopyResults();
        
        // 13. Log the successful action
        $this->logSuccessfulAction($result, $currentWeekRange, $currentWeekFolder);
        
        // 14. Show success summary
        $this->showSuccessSummary($result, $currentWeekRange, $currentWeekFolder);
        
        return 0;
    }
    
    private function showBanner()
    {
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║     MENU WEEK SWITCHING TOOL            ║');
        $this->info('║     ADMIN AUTHENTICATION REQUIRED       ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->line("");
    }
    
    private function authenticateAdmin()
    {
        $maxAttempts = 3;
        $attempt = 1;
        
        while ($attempt <= $maxAttempts) {
            $this->info("Attempt {$attempt}/{$maxAttempts}");
            
            $username = $this->ask('Admin Username:');
            $password = $this->secret('Password:');
            
            $user = User::where('username', $username)->first();
            
            if (!$user) {
                $this->error("User '{$username}' not found.");
                $attempt++;
                continue;
            }
            
            if ($user->role !== 'admin') {
                $this->error("User '{$username}' is not an admin. Role: {$user->role}");
                $this->error("Only users with 'admin' role can execute this command.");
                $attempt++;
                continue;
            }
            
            if (!Hash::check($password, $user->password)) {
                $this->error("Invalid password for user '{$username}'.");
                $attempt++;
                continue;
            }
            
            $this->info("✅ Authentication successful!");
            return $user;
        }
        
        $this->error("❌ Maximum authentication attempts reached.");
        
        $admins = User::where('role', 'admin')
            ->limit(5)
            ->get(['username', 'name']);
            
        if ($admins->count() > 0) {
            $this->line("");
            $this->warn("Available admin users:");
            foreach ($admins as $admin) {
                $this->info("  • {$admin->name} ({$admin->username})");
            }
        }
        
        return null;
    }
    
    private function showAdminInfo()
    {
        $this->line("");
        $this->info("👤 AUTHENTICATED ADMIN");
        $this->info("   Name: {$this->adminUser->name}");
        $this->info("   Username: {$this->adminUser->username}");
        $this->info("   Role: {$this->adminUser->role}");
        $this->info("   Company: " . ($this->adminUser->company ? $this->adminUser->company->name : 'N/A'));
        $this->line("");
    }
    
    private function getCurrentWeekRange()
    {
        $today = Carbon::now();
        $monday = $today->copy()->startOfWeek(Carbon::MONDAY);
        $thursday = $monday->copy()->addDays(3);
        
        return [
            'start' => $monday->format('Y-m-d'),
            'end' => $thursday->format('Y-m-d'),
            'monday' => $monday,
            'thursday' => $thursday
        ];
    }
    
    private function getNextWeekRange()
    {
        $today = Carbon::now();
        $nextMonday = $today->copy()->addWeek()->startOfWeek(Carbon::MONDAY);
        $nextThursday = $nextMonday->copy()->addDays(3);
        
        return [
            'start' => $nextMonday->format('Y-m-d'),
            'end' => $nextThursday->format('Y-m-d'),
            'monday' => $nextMonday,
            'thursday' => $nextThursday
        ];
    }
    
    private function getWeekFolderName(Carbon $mondayDate)
    {
        // Always use Monday's year and month
        $year = $mondayDate->format('Y');
        $month = $mondayDate->format('m');
        
        // Count which Monday of the month this is (1st, 2nd, 3rd, 4th, or 5th)
        $firstDayOfMonth = $mondayDate->copy()->firstOfMonth();
        
        // Find first Monday of the month
        $firstMonday = $firstDayOfMonth->copy();
        while ($firstMonday->dayOfWeekIso !== 1) {
            $firstMonday->addDay();
        }
        
        // Calculate weeks difference
        $weeksDiff = floor($mondayDate->diffInDays($firstMonday) / 7);
        $weekOfMonth = $weeksDiff + 1; // Convert to 1-based
        
        // Check if week (Mon-Thu) crosses month boundary
        $thursday = $mondayDate->copy()->addDays(3);
        $crossesMonth = $thursday->month !== $mondayDate->month;
        
        // If week crosses month boundary, it's ALWAYS W5
        if ($crossesMonth) {
            $weekOfMonth = 5;
        } else {
            // Otherwise, cap at W4 (normal weeks within a month)
            $weekOfMonth = min($weekOfMonth, 4);
        }
        
        // Format: yyyy-mm-w1 through yyyy-mm-w5
        return sprintf('%s-%s-w%d', $year, $month, $weekOfMonth);
    }
    
    private function getWeekCodePart(Carbon $mondayDate)
    {
        // Get the week part for the code: YYYY-MM-W{week}
        $year = $mondayDate->format('Y');
        $month = $mondayDate->format('m');
        
        // Count which Monday of the month this is
        $firstDayOfMonth = $mondayDate->copy()->firstOfMonth();
        $firstMonday = $firstDayOfMonth->copy();
        while ($firstMonday->dayOfWeekIso !== 1) {
            $firstMonday->addDay();
        }
        
        $weeksDiff = floor($mondayDate->diffInDays($firstMonday) / 7);
        $weekOfMonth = $weeksDiff + 1;
        
        // Check if week crosses month boundary
        $thursday = $mondayDate->copy()->addDays(3);
        $crossesMonth = $thursday->month !== $mondayDate->month;
        
        if ($crossesMonth) {
            $weekOfMonth = 5;
        } else {
            $weekOfMonth = min($weekOfMonth, 4);
        }
        
        return sprintf('%s-%s-W%d', $year, $month, $weekOfMonth);
    }
    
    private function getDayFolderName(Carbon $date)
    {
        $dayMap = [
            1 => 'mon', // Monday
            2 => 'tue', // Tuesday
            3 => 'wed', // Wednesday
            4 => 'thu', // Thursday
        ];
        
        $dayOfWeek = $date->dayOfWeekIso; // 1=Monday, 7=Sunday
        
        return $dayMap[$dayOfWeek] ?? 'unknown';
    }
    
    private function getDayCodeName(Carbon $date)
    {
        $dayMap = [
            1 => 'MON', // Monday
            2 => 'TUE', // Tuesday
            3 => 'WED', // Wednesday
            4 => 'THU', // Thursday
        ];
        
        $dayOfWeek = $date->dayOfWeekIso;
        
        return $dayMap[$dayOfWeek] ?? 'UNK';
    }
    
    private function generateMenuCode(Carbon $date, $weekCodePart)
    {
        $dayCode = $this->getDayCodeName($date);
        
        // Generate option number for this day
        $dateKey = $date->format('Y-m-d');
        if (!isset($this->optionCounters[$dateKey])) {
            $this->optionCounters[$dateKey] = 1;
        } else {
            $this->optionCounters[$dateKey]++;
        }
        
        $optionNumber = $this->optionCounters[$dateKey];
        
        // Format: YYYY-MM-W{week}-DAY-{optionNumber}
        return sprintf('%s-%s-%d', $weekCodePart, $dayCode, $optionNumber);
    }
    
    private function showWeekInfo($currentWeekRange, $nextWeekRange, $currentWeekFolder, $nextWeekFolder)
    {
        $this->info("📅 WEEK ANALYSIS");
        $this->info("   Current week: {$currentWeekRange['start']} to {$currentWeekRange['end']}");
        $this->info("   Week folder : {$currentWeekFolder}");
        $this->info("   Next week:    {$nextWeekRange['start']} to {$nextWeekRange['end']}");
        $this->info("   Week folder : {$nextWeekFolder}");
        $this->line("");
    }
    
    private function checkAndPrepareImages($nextWeekMenus, $nextWeekFolder, $currentWeekFolder)
    {
        $this->info("🖼️  CHECKING IMAGE FILES");
        
        foreach ($nextWeekMenus as $menu) {
            if (empty($menu->image)) {
                $this->warn("   Menu '{$menu->name}' has no image");
                $this->imageCopies[$menu->code] = [
                    'source' => null,
                    'destination' => null,
                    'status' => 'no_image'
                ];
                continue;
            }
            
            $imagePath = $menu->image;
            $pathParts = explode('/', $imagePath);
            
            if (count($pathParts) < 5) {
                $this->error("   Invalid image path format: {$imagePath}");
                $this->imageCopies[$menu->code] = [
                    'source' => $imagePath,
                    'destination' => null,
                    'status' => 'invalid_path'
                ];
                $this->imageErrors[] = "Invalid path format: {$imagePath}";
                continue;
            }
            
            $sourcePath = $imagePath;
            $dayFolder = $pathParts[2]; // mon/tue/wed/thu
            $usernameFolder = $pathParts[3]; // Original uploader's username
            $filename = $pathParts[4];
            
            // Keep the SAME username folder
            $destinationPath = "menus/{$currentWeekFolder}/{$dayFolder}/{$usernameFolder}/{$filename}";
            
            if (Storage::disk('public')->exists($sourcePath)) {
                $this->info("   ✓ Found: {$filename} (uploaded by: {$usernameFolder})");
                $this->imageCopies[$menu->code] = [
                    'source' => $sourcePath,
                    'destination' => $destinationPath,
                    'filename' => $filename,
                    'day_folder' => $dayFolder,
                    'username_folder' => $usernameFolder,
                    'status' => 'ready'
                ];
            } else {
                $this->error("   ✗ Missing: {$filename}");
                $this->imageCopies[$menu->code] = [
                    'source' => $sourcePath,
                    'destination' => $destinationPath,
                    'status' => 'missing'
                ];
                $this->imageErrors[] = "Image not found: {$filename} for menu '{$menu->name}'";
            }
        }
        
        $this->line("");
    }
    
    private function createImageFolders($weekFolder, $nextWeekMenus)
    {
        $this->info("📁 CREATING IMAGE FOLDERS");
        
        // Get all unique username/day combinations from the menus
        $foldersToCreate = [];
        
        foreach ($nextWeekMenus as $menu) {
            if (!empty($menu->image)) {
                $pathParts = explode('/', $menu->image);
                if (count($pathParts) >= 5) {
                    $dayFolder = $pathParts[2]; // mon/tue/wed/thu
                    $usernameFolder = $pathParts[3]; // Original uploader
                    
                    $folderPath = "menus/{$weekFolder}/{$dayFolder}/{$usernameFolder}";
                    $foldersToCreate[$folderPath] = true;
                }
            }
        }
        
        // REMOVED: Don't create empty folders for admin user
        
        $allCreated = true;
        
        foreach (array_keys($foldersToCreate) as $folderPath) {
            $this->info("   Creating: {$folderPath}");
            
            if (Storage::disk('public')->exists($folderPath)) {
                $this->info("     ✓ Already exists");
                continue;
            }
            
            try {
                $created = Storage::disk('public')->makeDirectory($folderPath, 0755, true);
                if ($created) {
                    $this->info("     ✓ Created successfully");
                } else {
                    $this->error("     ✗ Failed to create folder");
                    $allCreated = false;
                }
            } catch (Exception $e) {
                $this->error("     ✗ Error: " . $e->getMessage());
                $allCreated = false;
            }
        }
        
        return $allCreated;
    }
    
    private function showPreview($nextWeekMenus, $currentMenus, $nextWeekFolder, $currentWeekFolder)
    {
        $this->info("📋 PREVIEW OF CHANGES");
        
        // Reset option counters for preview
        $this->optionCounters = [];
        
        $rows = $nextWeekMenus->map(function($menu) use ($currentMenus, $nextWeekFolder, $currentWeekFolder) {
            $newDate = Carbon::parse($menu->menu_date)->subDays(7);
            $existingMenu = $currentMenus->firstWhere('menu_date', $newDate->format('Y-m-d'));
            
            $status = $existingMenu ? '🔄 Replace' : '🆕 New';
            $voteInfo = $existingMenu ? "({$existingMenu->chosenMenus->count()} votes)" : '';
            
            $newDayFolder = $this->getDayFolderName($newDate);
            
            // Generate preview code
            $weekCodePart = $this->getWeekCodePart(Carbon::parse($menu->menu_date)->subDays(7));
            $dateKey = $newDate->format('Y-m-d');
            if (!isset($this->optionCounters[$dateKey])) {
                $this->optionCounters[$dateKey] = 1;
            } else {
                $this->optionCounters[$dateKey]++;
            }
            $optionNumber = $this->optionCounters[$dateKey];
            $previewCode = sprintf('%s-%s-%d', $weekCodePart, $this->getDayCodeName($newDate), $optionNumber);
            
            $imageInfo = 'No image';
            if (!empty($menu->image)) {
                $imageStatus = $this->imageCopies[$menu->code]['status'] ?? 'unknown';
                if ($imageStatus === 'ready') {
                    $filename = $this->imageCopies[$menu->code]['filename'] ?? 'unknown';
                    $username = $this->imageCopies[$menu->code]['username_folder'] ?? 'unknown';
                    $imageInfo = "✓ {$filename} → {$username}/";
                } elseif ($imageStatus === 'missing') {
                    $imageInfo = '⚠️ Image missing';
                } else {
                    $imageInfo = '? Unknown';
                }
            }
            
            return [
                $status,
                $newDate->format('Y-m-d'),
                $newDate->format('D') . " ({$newDayFolder})",
                $previewCode,
                $menu->name,
                $menu->catering,
                $imageInfo,
                $voteInfo
            ];
        });
        
        $this->table(['Status', 'Date', 'Day', 'Code', 'Menu Name', 'Catering', 'Image', 'Info'], $rows);
        $this->line("");
    }
    
    private function showCurrentWeekError($currentMenus)
    {
        $this->error("❌ CURRENT WEEK HAS EXISTING MENUS");
        $this->error("   Found {$currentMenus->count()} menu(s) already created.");
        
        $this->table(['Date', 'Day', 'Code', 'Menu Name', 'Catering', 'Votes'], 
            $currentMenus->map(function($menu) {
                return [
                    $menu->menu_date->format('Y-m-d'),
                    $menu->menu_date->format('D'),
                    $menu->code,
                    $menu->name,
                    $menu->catering,
                    $menu->chosenMenus->count()
                ];
            })
        );
        
        $this->line("");
        $this->info("If you want to override these menus, use:");
        $this->info("   php artisan menu:switch-to-current-week --force");
        $this->warn("⚠️  Warning: Using --force will delete existing votes!");
    }
    
    private function showNextWeekError()
    {
        $this->error("❌ NO MENUS FOUND FOR NEXT WEEK");
        $this->error("   There are no menu options to copy from next week.");
        
        $this->line("");
        $this->info("Next steps:");
        $this->info("   1. Login to the web interface as admin");
        $this->info("   2. Create menu options for next week");
        $this->info("   3. Run this command again");
    }
    
    private function showWarnings($nextWeekMenus, $currentMenus)
    {
        $nextWeekVotes = ChosenMenu::whereIn('menu_code', $nextWeekMenus->pluck('code'))->count();
        if ($nextWeekVotes > 0) {
            $this->warn("⚠️  Next week already has {$nextWeekVotes} votes (unexpected)");
        }
        
        if ($currentMenus->count() > 0 && $this->option('force')) {
            $totalVotes = $currentMenus->sum(function($menu) {
                return $menu->chosenMenus->count();
            });
            
            if ($totalVotes > 0) {
                $this->error("⚠️  WARNING: Using --force will delete {$totalVotes} existing votes!");
            }
        }
        
        if (!empty($this->imageErrors)) {
            $this->warn("⚠️  Some images may be missing:");
            foreach ($this->imageErrors as $error) {
                $this->warn("   • {$error}");
            }
            $this->line("");
        }
    }
    
    private function showDryRunComplete()
    {
        $this->info("\n✅ DRY RUN COMPLETED");
        $this->info("   No changes were made to the database or filesystem.");
        $this->info("   Remove the --dry-run flag to execute.");
    }
    
    private function confirmExecution($nextWeekMenus, $currentMenus)
    {
        if ($this->option('no-interaction')) {
            $this->info("\n🚀 Executing automatically (--no-interaction mode)");
            return true;
        }
        
        $this->line("");
        $this->warn("🚀 READY TO EXECUTE");
        
        if (!$this->confirm("Copy {$nextWeekMenus->count()} menu(s) to current week?", false)) {
            $this->info("Operation cancelled.");
            return false;
        }
        
        if ($currentMenus->count() > 0 && $this->option('force')) {
            $totalVotes = $currentMenus->sum(function($menu) {
                return $menu->chosenMenus->count();
            });
            
            if ($totalVotes > 0) {
                $this->error("‼️  FINAL WARNING: This will PERMANENTLY delete {$totalVotes} votes!");
                if (!$this->confirm("Are you ABSOLUTELY sure?", false)) {
                    $this->info("Operation cancelled.");
                    return false;
                }
            }
        }
        
        $missingImages = count(array_filter($this->imageCopies, function($copy) {
            return $copy['status'] === 'missing';
        }));
        
        if ($missingImages > 0) {
            $this->warn("⚠️  {$missingImages} image(s) appear to be missing.");
            if (!$this->confirm("Continue anyway?", false)) {
                $this->info("Operation cancelled.");
                return false;
            }
        }
        
        return true;
    }
    
    private function copyImageFile($sourcePath, $destinationPath)
    {
        try {
            if (!Storage::disk('public')->exists($sourcePath)) {
                return [
                    'success' => false,
                    'message' => "Source file does not exist: {$sourcePath}"
                ];
            }
            
            $fileContents = Storage::disk('public')->get($sourcePath);
            
            $destinationDir = dirname($destinationPath);
            if (!Storage::disk('public')->exists($destinationDir)) {
                Storage::disk('public')->makeDirectory($destinationDir, 0755, true);
            }
            
            $copied = Storage::disk('public')->put($destinationPath, $fileContents);
            
            if ($copied) {
                return [
                    'success' => true,
                    'message' => "Image copied successfully"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Failed to copy image"
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ];
        }
    }
    
    private function executeSwitch($nextWeekMenus, $currentWeekRange, $currentWeekFolder, $nextWeekFolder)
    {
        $copiedCount = 0;
        $deletedVotes = 0;
        $imagesCopied = 0;
        $imagesFailed = 0;
        
        // Reset option counters
        $this->optionCounters = [];
        
        DB::beginTransaction();
        
        try {
            if ($this->option('force')) {
                $currentMenus = Menu::whereBetween('menu_date', [
                    $currentWeekRange['start'],
                    $currentWeekRange['end']
                ])->get();
                
                foreach ($currentMenus as $menu) {
                    $voteCount = $menu->chosenMenus()->count();
                    if ($voteCount > 0) {
                        $menu->chosenMenus()->delete();
                        $deletedVotes += $voteCount;
                        $this->warn("Deleted {$voteCount} votes for menu: {$menu->name}");
                    }
                    
                    if (!empty($menu->image) && Storage::disk('public')->exists($menu->image)) {
                        Storage::disk('public')->delete($menu->image);
                        $this->info("Deleted image: {$menu->image}");
                    }
                    
                    $menu->delete();
                    $this->info("Deleted existing menu: {$menu->name}");
                }
            }
            
            foreach ($nextWeekMenus as $menu) {
                $newDate = Carbon::parse($menu->menu_date)->subDays(7);
                $newDayFolder = $this->getDayFolderName($newDate);
                
                // Generate the new menu code
                $weekCodePart = $this->getWeekCodePart($newDate);
                $newCode = $this->generateMenuCode($newDate, $weekCodePart);
                
                $newImagePath = null;
                if (!empty($menu->image) && isset($this->imageCopies[$menu->code])) {
                    $imageInfo = $this->imageCopies[$menu->code];
                    
                    if ($imageInfo['status'] === 'ready') {
                        // Use the original username folder
                        $imageInfo['destination'] = "menus/{$currentWeekFolder}/{$newDayFolder}/{$imageInfo['username_folder']}/{$imageInfo['filename']}";
                        
                        $copyResult = $this->copyImageFile(
                            $imageInfo['source'],
                            $imageInfo['destination']
                        );
                        
                        if ($copyResult['success']) {
                            $newImagePath = $imageInfo['destination'];
                            $imagesCopied++;
                            $this->info("Copied image: {$imageInfo['filename']} → {$imageInfo['username_folder']}/");
                        } else {
                            $imagesFailed++;
                            $this->error("Failed to copy image: {$copyResult['message']}");
                            $newImagePath = null;
                        }
                    }
                }
                
                Menu::create([
                    'code' => $newCode, // Using the generated code format
                    'name' => $menu->name,
                    'menu_date' => $newDate->format('Y-m-d'),
                    'image' => $newImagePath ?? $menu->image,
                    'catering' => $menu->catering,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $copiedCount++;
                $this->info("Created menu {$newCode} for {$newDate->format('D Y-m-d')}: {$menu->name} ({$menu->catering})");
            }
            
            if (!$this->option('keep-next')) {
                $deletedCount = Menu::whereIn('code', $nextWeekMenus->pluck('code'))->delete();
                $this->info("Deleted {$deletedCount} original menu(s) from next week");
            } else {
                $this->info("Kept original next week menus (--keep-next option)");
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'copied_count' => $copiedCount,
                'deleted_votes' => $deletedVotes,
                'images_copied' => $imagesCopied,
                'images_failed' => $imagesFailed,
                'kept_original' => $this->option('keep-next'),
                'forced' => $this->option('force'),
                'week_folder' => $currentWeekFolder
            ];
            
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function showImageCopyResults()
    {
        $this->line("");
        $this->info("🖼️  IMAGE COPY RESULTS");
        
        $imageCopies = array_filter($this->imageCopies, function($copy) {
            return $copy['status'] === 'ready';
        });
        
        if (empty($imageCopies)) {
            $this->info("   No images to copy");
        } else {
            foreach ($imageCopies as $menuCode => $copyInfo) {
                $filename = $copyInfo['filename'] ?? 'unknown';
                $username = $copyInfo['username_folder'] ?? 'unknown';
                $status = "✓ Copied to {$username}/";
                $this->info("   {$status}: {$filename}");
            }
        }
    }
    
    private function showSuccessSummary($result, $currentWeekRange, $currentWeekFolder)
    {
        $this->line("");
        $this->info("╔══════════════════════════════════════════╗");
        $this->info("║           OPERATION SUCCESSFUL           ║");
        $this->info("╚══════════════════════════════════════════╝");
        $this->line("");
        
        $this->info("📊 SUMMARY");
        $this->table(['Metric', 'Value'], [
            ['Menus Copied', $result['copied_count']],
            ['Votes Deleted', $result['deleted_votes']],
            ['Images Copied', $result['images_copied']],
            ['Images Failed', $result['images_failed']],
            ['Week Folder', $result['week_folder']],
            ['Original Menus Kept', $result['kept_original'] ? 'Yes' : 'No'],
            ['Force Mode Used', $result['forced'] ? 'Yes' : 'No'],
            ['Executed By', $this->adminUser->name],
            ['Timestamp', now()->format('Y-m-d H:i:s')],
        ]);
        
        $currentMenus = Menu::whereBetween('menu_date', [
            $currentWeekRange['start'],
            $currentWeekRange['end']
        ])->orderBy('menu_date')->orderBy('catering')->get();
        
        $this->line("");
        $this->info("📅 CURRENT WEEK MENUS");
        
        if ($currentMenus->count() > 0) {
            $this->table(['Code', 'Date', 'Day', 'Menu Name', 'Catering', 'Image'], 
                $currentMenus->map(function($menu) {
                    $imageName = $menu->image ? '✓ ' . basename($menu->image) : '✗ No image';
                    return [
                        $menu->code,
                        $menu->menu_date->format('Y-m-d'),
                        $menu->menu_date->format('D'),
                        $menu->name,
                        $menu->catering,
                        $imageName
                    ];
                })
            );
        } else {
            $this->warn("No menus found for current week (unexpected)");
        }
        
        // Show folder structure with usernames
        $this->line("");
        $this->info("📁 FOLDER STRUCTURE CREATED:");
        
        // Get all unique username folders that were created
        $createdFolders = [];
        foreach ($this->imageCopies as $copyInfo) {
            if ($copyInfo['status'] === 'ready' && isset($copyInfo['username_folder'])) {
                $day = $copyInfo['day_folder'] ?? 'unknown';
                $username = $copyInfo['username_folder'];
                $folderPath = "menus/{$currentWeekFolder}/{$day}/{$username}";
                $createdFolders[$folderPath] = true;
            }
        }
        
        foreach (array_keys($createdFolders) as $folderPath) {
            if (Storage::disk('public')->exists($folderPath)) {
                $files = Storage::disk('public')->files($folderPath);
                $this->info("   📂 {$folderPath}");
                $this->info("     Files: " . count($files));
                if (count($files) > 0) {
                    foreach ($files as $file) {
                        $this->info("       • " . basename($file));
                    }
                }
            }
        }
        
        $this->line("");
        $this->info("📝 NEXT STEPS");
        $this->info("   1. Karyawan can now vote for this week's menus");
        $this->info("   2. Create new menus for the following week");
        $this->info("   3. Image URLs will use the new week folder: {$currentWeekFolder}");
        
        if ($result['deleted_votes'] > 0) {
            $this->line("");
            $this->warn("⚠️  IMPORTANT: {$result['deleted_votes']} votes were deleted.");
            $this->warn("   Affected users may need to vote again.");
        }
        
        if ($result['images_failed'] > 0) {
            $this->line("");
            $this->warn("⚠️  {$result['images_failed']} image(s) failed to copy.");
            $this->warn("   You may need to upload images manually.");
        }
        
        $this->line("");
        $this->info("✅ Command completed successfully!");
    }
    
    private function logSuccessfulAction($result, $currentWeekRange, $currentWeekFolder)
    {
        Log::channel('admin_actions')->info('Menu week switch executed', [
            'admin_id' => $this->adminUser->id,
            'admin_username' => $this->adminUser->username,
            'admin_name' => $this->adminUser->name,
            'action' => 'menu_week_switch',
            'copied_count' => $result['copied_count'],
            'deleted_votes' => $result['deleted_votes'],
            'images_copied' => $result['images_copied'],
            'images_failed' => $result['images_failed'],
            'force_used' => $result['forced'],
            'keep_original' => $result['kept_original'],
            'week_start' => $currentWeekRange['start'],
            'week_end' => $currentWeekRange['end'],
            'week_folder' => $currentWeekFolder,
            'executed_at' => now()->toDateTimeString(),
        ]);
    }
}