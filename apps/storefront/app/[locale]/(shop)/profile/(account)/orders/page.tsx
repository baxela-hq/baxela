"use client";

import { Suspense, useState } from "react";
import { useSearchParams } from "next/navigation";
import { OrderList } from "@/components/account/order-list";
import {
  OrdersToolbar,
  type OrderStatusFilter,
} from "@/components/account/orders-toolbar";

function OrdersView() {
  // ?q= seeds the search (deep-links from notifications by order code);
  // from then on the toolbar owns the query, like the ?edit=1 pattern.
  const searchParams = useSearchParams();
  const [search, setSearch] = useState(searchParams.get("q") ?? "");
  const [statusFilter, setStatusFilter] = useState<OrderStatusFilter>("all");

  return (
    <div>
      <OrdersToolbar
        search={search}
        onSearchChange={setSearch}
        status={statusFilter}
        onStatusChange={setStatusFilter}
      />
      <div className="mt-8">
        <OrderList search={search} statusFilter={statusFilter} />
      </div>
    </div>
  );
}

export default function ProfileOrdersPage() {
  return (
    <Suspense>
      <OrdersView />
    </Suspense>
  );
}
