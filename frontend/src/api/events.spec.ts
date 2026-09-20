import axios from "axios";
import { describe, expect, it } from "vitest";

describe("timeline query serialization", () => {
  it("sends arrays as type[]=a&type[]=b, which Laravel reads as arrays", () => {
    const uri = axios.getUri({ url: "/api/v1/timeline", params: { type: ["event", "artwork"], theme_id: [3] }, paramsSerializer: { indexes: false } });

    expect(decodeURIComponent(uri)).toBe("/api/v1/timeline?type[]=event&type[]=artwork&theme_id[]=3");
  });
});
