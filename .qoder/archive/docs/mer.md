graph TD

  Dashboard["BOM Dashboard"]

  BOMDetail["BOM Detail (Read-Only)"]
  BOMEdit["BOM Edit / New Revision"]
  StatusModal["Status Change Modal"]
  RevisionHistory["Revision History"]
  Compare["Comparison View"]

  %% From dashboard to BOM
  Dashboard -->|View BOM| BOMDetail
  Dashboard -->|Create BOM (editor)| BOMEdit
  Dashboard -->|Compare selected BOMs| Compare

  %% Detail-level actions
  BOMDetail -->|Edit / New revision (editor)| BOMEdit
  BOMDetail -->|Change status| StatusModal
  BOMDetail -->|View history| RevisionHistory
  BOMDetail -->|Add to comparison| Compare

  %% Edit flow
  BOMEdit -->|Save new revision| BOMDetail
  BOMEdit -->|Cancel / discard| BOMDetail
  BOMEdit -->|Validation errors / banned components| BOMEdit
  BOMEdit -->|"Newer revision exists"| BOMEdit

  %% Status change
  StatusModal -->|Status changed| BOMDetail
  StatusModal -->|Disallowed transition| StatusModal

  %% History
  RevisionHistory -->|View specific revision| BOMDetail
  RevisionHistory -->|Compare with current| Compare

  %% Comparison
  Compare -->|Back to list / detail| Dashboard