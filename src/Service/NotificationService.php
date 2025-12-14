<?php
namespace App\Service;

use App\Repository\NotificationRepository;
use App\Repository\UserRepository;

class NotificationService
{
    private $repo;
    private $userRepo;

    public function __construct()
    {
        $this->repo = new NotificationRepository();
        $this->userRepo = new UserRepository();
    }

    public function getPendingNotifications($userId)
    {
        $perms = $this->userRepo->getUserApprovals($userId);
        
        $notifications = [];
        $totalCount = 0;

        if (in_array('KASBON', $perms)) {
            $count = $this->repo->countPendingKasbon();
            if ($count > 0) {
                $notifications[] = [
                    'type' => 'KASBON',
                    'title' => 'Approval Kasbon',
                    'count' => $count,
                    'desc' => $count . ' Pengajuan Kasbon menunggu persetujuan.',
                    'link' => '/kasbon',
                    'color' => 'warning'
                ];
                $totalCount += $count;
            }
        }

        if (in_array('INVOICE', $perms)) {
            $count = $this->repo->countPendingInvoice();
            if ($count > 0) {
                $notifications[] = [
                    'type' => 'INVOICE',
                    'title' => 'Approval Invoice',
                    'count' => $count,
                    'desc' => $count . ' Invoice Penjualan menunggu persetujuan.',
                    'link' => '/invoice',
                    'color' => 'info'
                ];
                $totalCount += $count;
            }
        }

        if (in_array('BILL', $perms)) {
            $count = $this->repo->countPendingBill();
            if ($count > 0) {
                $notifications[] = [
                    'type' => 'BILL',
                    'title' => 'Approval Tagihan',
                    'count' => $count,
                    'desc' => $count . ' Tagihan Vendor menunggu persetujuan.',
                    'link' => '/bill',
                    'color' => 'error'
                ];
                $totalCount += $count;
            }
        }

        if (in_array('REKON', $perms)) {
            $countM = $this->repo->countPendingRekonMandiri();
            if ($countM > 0) {
                $notifications[] = [
                    'type' => 'REKON',
                    'title' => 'Approval Rekon Mandiri',
                    'count' => $countM,
                    'desc' => $countM . ' Data Rekon Mandiri menunggu persetujuan.',
                    'link' => '/rekon',
                    'color' => 'primary'
                ];
                $totalCount += $countM;
            }

            $countC = $this->repo->countPendingRekonCimb();
            if ($countC > 0) {
                $notifications[] = [
                    'type' => 'REKON_CIMB',
                    'title' => 'Approval Rekon CIMB',
                    'count' => $countC,
                    'desc' => $countC . ' Data Rekon CIMB menunggu persetujuan.',
                    'link' => '/rekon/cimb',
                    'color' => 'error'
                ];
                $totalCount += $countC;
            }
        }

        return [
            'total_count' => $totalCount,
            'items' => $notifications
        ];
    }
}