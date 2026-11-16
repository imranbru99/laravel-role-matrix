# Conditional access

A grant stores JSON:

```json
{ "all": [ { "rule": "ownership", "owner_key": "user_id" } ], "any": [] }
```

`all` is a conjunction. `any` is a disjunction. Both apply when both are present.

| Rule | Record check | SQL scope |
| --- | --- | --- |
| `ownership` | `record.{owner_key} == user id` | `where` |
| `team` | record team is one of the user's teams | `whereIn` |
| `department` | same, for `department_id` | `whereIn` |
| `status` | value in or not in a list | `whereIn` |
| `amount` | numeric comparison, optional currency | `where` / `whereBetween` |
| `attribute` | `record.field` compared with a literal, `user.field`, or `context.key` | only for literals |
| `time_window` | weekdays, dates, hours | precondition only |
| `ip_range` | request IP in a CIDR | precondition only |
| custom | your closure or class | no |

`HasConditionalAccess::scopeAccessibleBy` skips the query (`whereRaw('1 = 0')`) when every grant fails its preconditions or the permission is denied. A super admin and an unconditional grant leave the query untouched.

Register a rule with `RoleMatrix::condition('name', ClassOrClosure::class)`. The class implements `ConditionContract` and can publish a `schema()` array for the matrix drawer.
