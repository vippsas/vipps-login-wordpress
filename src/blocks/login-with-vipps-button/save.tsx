import { useBlockProps } from "@wordpress/block-editor";

export default function save() {
  return (
    <p {...useBlockProps.save()}>
      {"Login with Vipps&#x2F;MobilePay-button – hello from the saved content!"}
    </p>
  );
}
