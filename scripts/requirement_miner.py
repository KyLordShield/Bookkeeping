#!/usr/bin/env python3
import json
import sys
from collections import Counter


def _dynamic_limit(transactions, min_floor, max_cap):
    if not transactions:
        return min_floor
    avg_len = sum(len(tx) for tx in transactions) / len(transactions)
    return max(min_floor, min(max_cap, round(avg_len)))


def mine_service(transactions, min_confidence, min_support_count, min_floor, max_cap):
    total_requests = len(transactions)
    if total_requests == 0:
        return {"suggestions": [], "insights": []}

    counts = Counter()
    for tx in transactions:
        for req in set(tx):
            name = str(req).strip()
            if name:
                counts[name] += 1

    rows = []
    for name, usage in counts.items():
        confidence_pct = round((usage / total_requests) * 100.0, 1)
        rows.append(
            {
                "requirement_name": name,
                "usage_count": usage,
                "total_requests": total_requests,
                "confidence_pct": confidence_pct,
            }
        )

    rows.sort(key=lambda x: (-x["usage_count"], x["requirement_name"].lower()))
    insights = rows[:10]

    accepted = [
        r
        for r in rows
        if r["usage_count"] >= min_support_count
        and (r["confidence_pct"] / 100.0) >= min_confidence
    ]
    accepted.sort(key=lambda x: (-x["usage_count"], x["requirement_name"].lower()))

    limit = _dynamic_limit(transactions, min_floor, max_cap)
    suggestions = [r["requirement_name"] for r in accepted[:limit]]
    if not suggestions and rows:
        suggestions = [rows[0]["requirement_name"]]

    return {"suggestions": suggestions, "insights": insights}


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "missing input file"}))
        return 1

    with open(sys.argv[1], "r", encoding="utf-8") as f:
        payload = json.load(f)

    service_transactions = payload.get("service_transactions", {})
    min_confidence = float(payload.get("min_confidence", 0.6))
    min_support_count = int(payload.get("min_support_count", 1))
    min_floor = int(payload.get("min_suggestions_floor", 3))
    max_cap = int(payload.get("max_suggestions_cap", 7))

    result = {"services": {}}
    for sid, txs in service_transactions.items():
        clean_txs = []
        for tx in txs or []:
            if isinstance(tx, list):
                clean_txs.append([str(x).strip() for x in tx if str(x).strip()])
        result["services"][str(sid)] = mine_service(
            clean_txs, min_confidence, min_support_count, min_floor, max_cap
        )

    print(json.dumps(result, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
