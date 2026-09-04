"use client";

import { useState } from "react";
import { OrderList } from "@/components/account/order-list";
import {
  OrdersToolbar,
  type OrderStatusFilter,
} from "@/components/account/orders-toolbar";

export default function ProfileOrdersPage() {
  const [search, setSearch] = useState("");
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
